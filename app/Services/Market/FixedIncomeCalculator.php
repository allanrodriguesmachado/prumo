<?php

namespace App\Services\Market;

use App\Models\Asset;
use App\Models\MarketIndex;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Marcação estimada (bruta, antes de IR) de renda fixa usando as séries
 * oficiais do Banco Central armazenadas localmente.
 */
class FixedIncomeCalculator
{
    private array $memo = [];

    public function valueOf(Asset $asset, Collection $ops): float
    {
        $value = 0.0;
        foreach ($ops as $op) {
            $f = $this->factor($asset, Carbon::parse($op->date), now());
            if ($op->type === 'buy') {
                $value += (float) $op->total * $f;
            } elseif ($op->type === 'sell') {
                $value -= (float) $op->total * $f;
            }
        }

        return max(0, $value);
    }

    public function factor(Asset $asset, Carbon $from, Carbon $to): float
    {
        if ($from->gte($to)) {
            return 1.0;
        }
        $end = $asset->maturity_date && $asset->maturity_date->lt($to) ? $asset->maturity_date : $to;
        $rate = (float) ($asset->rate ?? 0);
        $years = $from->diffInDays($end) / 365;

        return match ($asset->class === 'savings' ? 'savings' : $asset->indexer) {
            'cdi' => $this->accumulateDaily('cdi', $from, $end, ($rate ?: 100) / 100, 10.5),
            'selic' => $this->accumulateDaily('selic', $from, $end, 1, 10.5) * pow(1 + $rate / 100, $years),
            'ipca' => $this->accumulateMonthly('ipca', $from, $end, 0.4) * pow(1 + $rate / 100, $years),
            'savings' => $this->accumulateMonthly('savings', $from, $end, 0.55),
            default => pow(1 + $rate / 100, $years), // prefixado
        };
    }

    private function series(string $code): Collection
    {
        return $this->memo[$code] ??= MarketIndex::where('code', $code)->orderBy('date')->get(['date', 'value'])
            ->mapWithKeys(fn ($r) => [$r->date->toDateString() => $r->value]);
    }

    /** Produto de (1 + taxa diária × multiplicador). Dias sem dado usam a última taxa conhecida. */
    private function accumulateDaily(string $code, Carbon $from, Carbon $to, float $mult, float $fallbackAnnual): float
    {
        $series = $this->series($code);
        $factor = 1.0;
        $lastKnown = $series->last() ?? (pow(1 + $fallbackAnnual / 100, 1 / 252) - 1) * 100;
        $lastDate = $series->keys()->last();
        foreach ($series as $date => $value) {
            if ($date >= $from->toDateString() && $date < $to->toDateString()) {
                $factor *= 1 + ($value / 100) * $mult;
            }
        }
        // dias úteis após o último dado disponível
        $gapStart = $lastDate ? Carbon::parse($lastDate)->addDay()->max($from) : $from->copy();
        $gapDays = 0;
        for ($d = $gapStart->copy(); $d->lt($to); $d->addDay()) {
            if (! $d->isWeekend()) {
                $gapDays++;
            }
        }

        return $factor * pow(1 + ($lastKnown / 100) * $mult, $gapDays);
    }

    private function accumulateMonthly(string $code, Carbon $from, Carbon $to, float $fallbackMonthly): float
    {
        $series = $this->series($code)->mapWithKeys(fn ($v, $d) => [substr($d, 0, 7) => $v]);
        $factor = 1.0;
        $cursor = $from->copy()->startOfMonth()->addMonth();
        $last = $series->last() ?? $fallbackMonthly;
        while ($cursor->lte($to)) {
            $factor *= 1 + ($series[$cursor->format('Y-m')] ?? $last) / 100;
            $cursor->addMonth();
        }
        // pró-rata do mês corrente
        $factor *= pow(1 + $last / 100, ($to->day - 1) / max(1, $to->daysInMonth) * ($from->isSameMonth($to) ? 0 : 1));

        return $factor;
    }
}
