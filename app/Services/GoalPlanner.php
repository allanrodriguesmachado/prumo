<?php

namespace App\Services;

use App\Models\Goal;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Metas inteligentes: calcula quanto guardar por mês e se cabe no orçamento,
 * dividindo a capacidade de poupança real entre as metas ativas.
 */
class GoalPlanner
{
    public function __construct(private FinanceAnalytics $a) {}

    /** Quanto sobra por mês, em média, depois de todos os gastos (inclui fixos, parcelas e dívidas pagas). */
    public function monthlyCapacity(): float
    {
        return round(max(0, $this->a->monthlyIncome() - $this->a->averageMonthlySpending()), 2);
    }

    public function evaluate(Collection $goals): array
    {
        $capacity = $this->monthlyCapacity();
        $active = $goals->filter(fn ($g) => ! $g->achieved_at && $g->remaining() > 0);
        $needed = $active->sum(fn ($g) => (float) ($g->monthlyNeeded() ?? 0));
        $out = [];

        foreach ($active as $g) {
            $need = $g->monthlyNeeded();
            // fatia da capacidade: proporcional à necessidade (ou igual, se a meta não tem data)
            $share = $needed > 0 && $need ? $capacity * $need / $needed : ($active->count() ? $capacity / $active->count() : 0);
            $realisticMonths = $share > 0 ? (int) ceil($g->remaining() / $share) : null;

            $status = match (true) {
                $need === null => ['sem prazo', 'slate', $share > 0 ? 'No ritmo da sua sobra atual, leva cerca de '.$this->months($realisticMonths).'.' : 'Hoje não sobra dinheiro no mês para esta meta.'],
                $need <= $share => ['viável', 'emerald', 'Guardando '.brl($need).'/mês você chega lá no prazo.'],
                $need <= $capacity => ['apertado', 'amber', 'Cabe só se outras metas esperarem: exige '.brl($need).'/mês de uma sobra total de '.brl($capacity).'.'],
                default => ['inviável no prazo', 'rose', 'Exige '.brl($need).'/mês, mas sobra '.brl($capacity).'. '.($realisticMonths ? 'Data realista: '.month_name(now()->addMonths($realisticMonths), true).'/'.now()->addMonths($realisticMonths)->format('y').'.' : 'Reduza gastos ou aumente a renda antes.')],
            };
            $out[$g->id] = [
                'needed' => $need, 'share' => round($share, 2), 'status' => $status[0], 'color' => $status[1], 'text' => $status[2],
                'realistic_date' => $realisticMonths ? now()->addMonths($realisticMonths)->startOfMonth() : null,
            ];
        }

        return ['capacity' => $capacity, 'needed' => round($needed, 2), 'goals' => $out, 'overbooked' => $needed > $capacity];
    }

    /** Simulação para o formulário: valor-alvo, já guardado e data. */
    public function check(float $target, float $current, ?Carbon $date): array
    {
        $remaining = max(0, $target - $current);
        $capacity = $this->monthlyCapacity();
        $months = $date ? max(1, (int) ceil(now()->floatDiffInMonths($date, false))) : null;
        $monthly = $months ? $remaining / $months : null;

        return [
            'monthly' => $monthly ? round($monthly, 2) : null,
            'capacity' => $capacity,
            'feasible' => $monthly === null ? $capacity > 0 : $monthly <= $capacity,
            'realistic_months' => $capacity > 0 ? (int) ceil($remaining / $capacity) : null,
        ];
    }

    private function months(?int $n): string
    {
        if (! $n) {
            return '—';
        }

        return $n < 12 ? "{$n} meses" : num($n / 12, 1).' anos';
    }
}
