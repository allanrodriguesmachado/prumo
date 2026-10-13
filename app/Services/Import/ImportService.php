<?php

namespace App\Services\Import;

use App\Models\Import;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\UploadedFile;

class ImportService
{
    public function __construct(private StatementParser $parser, private TransactionService $transactions) {}

    /** Pré-visualização com categoria sugerida e marcação de duplicados. */
    public function preview(UploadedFile $file, array $target = []): array
    {
        $parsed = $this->parser->parse(file_get_contents($file->getRealPath()), $file->getClientOriginalName());
        $categorizer = app(Categorizer::class);
        $claimed = [];

        $rows = array_map(function ($r) use ($categorizer, $target, &$claimed) {
            $type = $r['amount'] >= 0 ? 'income' : 'expense';
            $hash = $this->hash($r);
            $duplicate = Transaction::where('import_hash', $hash)->exists();
            $match = $duplicate ? null : $this->findMatch($r, $type, $target, $claimed);
            if ($match) {
                $claimed[] = $match->id;
            }

            return $r + [
                'type' => $type,
                'hash' => $hash,
                'duplicate' => $duplicate,
                'category_id' => $match?->category_id ?? $categorizer->guess($r['description'], $type)?->id,
                'match_id' => $match?->id,
                'match_label' => $match ? $match->label().' ('.$match->date->format('d/m').')' : null,
            ];
        }, $parsed['rows']);

        return ['format' => $parsed['format'], 'rows' => $rows, 'filename' => $file->getClientOriginalName()];
    }

    public function commit(array $rows, array $target, string $filename, string $format): Import
    {
        $imported = 0; $skipped = 0; $reconciled = 0;
        foreach ($rows as $r) {
            if (empty($r['selected']) || Transaction::where('import_hash', $r['hash'])->exists()) {
                $skipped++;
                continue;
            }
            $amount = (float) $r['amount'];
            // conciliação: o lançamento já existia (manual ou previsto) — só confirma
            if (! empty($r['match_id']) && ($existing = Transaction::find($r['match_id'])) && ! $existing->import_hash) {
                $existing->update([
                    'paid' => true, 'reconciled_at' => now(), 'import_hash' => $r['hash'],
                    'date' => $r['date'], 'category_id' => $r['category_id'] ?: $existing->category_id,
                ]);
                $reconciled++;
                continue;
            }
            $this->transactions->create([
                'type' => $amount >= 0 ? 'income' : 'expense',
                'kind' => $amount >= 0 ? 'extra_income' : 'daily',
                'description' => $r['description'],
                'amount' => abs($amount),
                'date' => $r['date'],
                'paid' => true,
                'category_id' => $r['category_id'] ?: null,
                'account_id' => $target['account_id'] ?? null,
                'credit_card_id' => $target['credit_card_id'] ?? null,
                'is_shared' => (bool) ($target['is_shared'] ?? false),
                'source' => 'import',
                'import_hash' => $r['hash'],
                'reconciled_at' => now(),
            ]);
            if (! empty($r['category_id'])) {
                app(Categorizer::class)->learn($r['description'], (int) $r['category_id']);
            }
            $imported++;
        }

        $import = Import::create(['filename' => $filename, 'format' => $format, 'imported' => $imported, 'skipped' => $skipped]);
        $import->reconciled = $reconciled;

        return $import;
    }

    /** Procura um lançamento já existente que corresponda à linha do extrato. */
    private function findMatch(array $r, string $type, array $target, array $claimed): ?Transaction
    {
        $date = \Carbon\Carbon::parse($r['date']);

        return Transaction::where('type', $type)
            ->whereNull('import_hash')->whereNull('reconciled_at')
            ->whereNotIn('id', $claimed ?: [0])
            ->whereBetween('amount', [abs($r['amount']) - 0.01, abs($r['amount']) + 0.01])
            ->whereBetween('date', [$date->copy()->subDays(3)->toDateString(), $date->copy()->addDays(3)->toDateString()])
            ->when(! empty($target['account_id']), fn ($q) => $q->where(fn ($w) => $w->where('account_id', $target['account_id'])->orWhereNull('account_id')))
            ->when(! empty($target['credit_card_id']), fn ($q) => $q->where('credit_card_id', $target['credit_card_id']))
            ->orderByRaw('abs(date - ?::date)', [$date->toDateString()])
            ->first();
    }

    private function hash(array $r): string
    {
        return hash('sha256', ($r['fitid'] ?: '').'|'.$r['date'].'|'.number_format($r['amount'], 2, '.', '').'|'.Categorizer::normalize($r['description']));
    }
}
