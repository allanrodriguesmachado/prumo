<?php

namespace App\Services\Market;

use App\Models\MarketIndex;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * API pública e gratuita do Banco Central (SGS) — sem chave.
 * https://dadosabertos.bcb.gov.br
 */
class BcbClient
{
    public const SERIES = [
        'cdi' => 12,        // CDI diário (% a.d.)
        'selic' => 11,      // Selic diária (% a.d.)
        'selic_meta' => 432, // meta Selic (% a.a.)
        'ipca' => 433,      // IPCA mensal (%)
        'ipca_12m' => 13522, // IPCA acumulado 12 meses
        'cdi_year' => 4389, // CDI anualizado base 252
        'savings' => 7828,  // rentabilidade mensal da poupança (% a.m.)
    ];

    private const BASE = 'https://api.bcb.gov.br/dados/serie/bcdata.sgs.%d/dados';

    /** Busca a série no período (janelas de até 10 anos, limite da API). */
    public function fetch(string $code, Carbon $from, ?Carbon $to = null): array
    {
        $to ??= now();
        $out = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $end = $cursor->copy()->addYears(2)->min($to);
            try {
                $res = Http::timeout(20)->retry(2, 500)->acceptJson()->get(sprintf(self::BASE, self::SERIES[$code]), [
                    'formato' => 'json', 'dataInicial' => $cursor->format('d/m/Y'), 'dataFinal' => $end->format('d/m/Y'),
                ]);
                if ($res->ok() && is_array($res->json())) {
                    foreach ($res->json() as $row) {
                        $out[] = ['date' => Carbon::createFromFormat('d/m/Y', $row['data'])->toDateString(), 'value' => (float) str_replace(',', '.', $row['valor'])];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("BCB {$code}: ".$e->getMessage());
            }
            $cursor = $end->copy()->addDay();
        }

        return $out;
    }

    public function latest(string $code): ?array
    {
        return Cache::remember("bcb:latest:{$code}", now()->addHours(6), function () use ($code) {
            try {
                $res = Http::timeout(15)->retry(2, 500)->acceptJson()->get(sprintf(self::BASE, self::SERIES[$code]).'/ultimos/1', ['formato' => 'json']);
                $row = $res->json()[0] ?? null;

                return $row ? ['date' => $row['data'], 'value' => (float) str_replace(',', '.', $row['valor'])] : null;
            } catch (\Throwable $e) {
                Log::warning("BCB latest {$code}: ".$e->getMessage());

                return null;
            }
        });
    }

    /** Sincroniza séries usadas no cálculo de renda fixa para a tabela local. */
    public function sync(string $code, int $yearsBack = 6): int
    {
        $last = MarketIndex::where('code', $code)->max('date');
        $from = $last ? Carbon::parse($last)->addDay() : now()->subYears($yearsBack)->startOfYear();
        if ($from->gt(now())) {
            return 0;
        }
        // janelas consecutivas podem repetir a data de fronteira
        $rows = array_values(collect($this->fetch($code, $from))->keyBy('date')->all());
        foreach (array_chunk($rows, 500) as $chunk) {
            MarketIndex::upsert(array_map(fn ($r) => $r + ['code' => $code], $chunk), ['code', 'date'], ['value']);
        }

        return count($rows);
    }

    /** Painel de indicadores (Selic, CDI, IPCA). */
    public function indicators(): array
    {
        return [
            'selic' => $this->latest('selic_meta'),
            'cdi' => $this->latest('cdi_year'),
            'ipca_12m' => $this->latest('ipca_12m'),
            'ipca' => $this->latest('ipca'),
        ];
    }
}
