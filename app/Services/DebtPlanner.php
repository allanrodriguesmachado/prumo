<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Simulador do plano de saída das dívidas.
 *
 * - avalanche: paga primeiro a dívida com maior juros (menor custo total)
 * - snowball (bola de neve): paga primeiro a menor dívida (vitórias rápidas)
 * - prumo (híbrido): ataca dívidas em atraso e juros abusivos (>5% a.m.)
 *   primeiro, depois bola de neve — equilíbrio entre matemática e motivação.
 */
class DebtPlanner
{
    public const STRATEGIES = [
        'prumo' => ['Prumo (híbrido)', 'Ataca primeiro o que está atrasado e os juros abusivos (rotativo, cheque especial), depois quita das menores para as maiores para gerar vitórias rápidas.'],
        'avalanche' => ['Avalanche', 'Quita primeiro a dívida com maior taxa de juros. É matematicamente a que paga menos juros no total.'],
        'snowball' => ['Bola de neve', 'Quita primeiro a menor dívida. Cada dívida eliminada libera dinheiro e dá motivação para seguir.'],
    ];

    /**
     * @param  Collection  $debts  Debt models ativos
     * @param  float  $monthlyBudget  valor total mensal disponível para dívidas
     */
    public function simulate(Collection $debts, float $monthlyBudget, string $strategy = 'prumo', int $maxMonths = 360): array
    {
        $list = $debts->map(fn ($d) => [
            'id' => $d->id, 'name' => $d->creditor, 'balance' => (float) $d->current_balance,
            'rate' => (float) $d->monthly_interest_rate / 100, 'min' => (float) $d->minimum_payment,
            'overdue' => (bool) $d->is_overdue, 'paid_month' => null, 'interest' => 0.0,
        ])->filter(fn ($d) => $d['balance'] > 0)->values()->all();

        if (! $list) {
            return ['months' => 0, 'interest' => 0, 'timeline' => [], 'order' => [], 'feasible' => true, 'debts' => []];
        }

        $minTotal = array_sum(array_column($list, 'min'));
        $timeline = [];
        $totalInterest = 0;
        $month = 0;
        $order = [];

        while ($month < $maxMonths && array_sum(array_column($list, 'balance')) > 0.01) {
            $month++;
            // juros do mês
            foreach ($list as &$d) {
                if ($d['balance'] > 0) {
                    $i = $d['balance'] * $d['rate'];
                    $d['balance'] += $i; $d['interest'] += $i; $totalInterest += $i;
                }
            }
            unset($d);

            $available = $monthlyBudget;
            // pagamentos mínimos
            foreach ($list as &$d) {
                if ($d['balance'] > 0) {
                    $p = min($d['balance'], $d['min'], $available);
                    $d['balance'] -= $p; $available -= $p;
                }
            }
            unset($d);

            // excedente vai para o alvo da estratégia
            foreach ($this->priority($list, $strategy) as $idx) {
                if ($available <= 0) {
                    break;
                }
                $p = min($list[$idx]['balance'], $available);
                $list[$idx]['balance'] -= $p; $available -= $p;
            }

            foreach ($list as &$d) {
                if ($d['balance'] <= 0.01 && $d['paid_month'] === null) {
                    $d['balance'] = 0; $d['paid_month'] = $month; $order[] = $d['name'];
                }
            }
            unset($d);

            $timeline[] = ['month' => $month, 'balance' => round(array_sum(array_column($list, 'balance')), 2)];

            // se a dívida está crescendo mesmo pagando tudo, o plano é inviável
            if ($month > 12 && $timeline[$month - 1]['balance'] >= $timeline[$month - 13]['balance']) {
                return ['months' => null, 'interest' => round($totalInterest, 2), 'timeline' => $timeline, 'order' => $order, 'feasible' => false, 'min_total' => $minTotal, 'debts' => $list];
            }
        }

        return [
            'months' => $month, 'interest' => round($totalInterest, 2), 'timeline' => $timeline, 'order' => $order,
            'feasible' => $month < $maxMonths, 'min_total' => $minTotal, 'debts' => $list,
            'free_date' => now()->addMonthsNoOverflow($month),
        ];
    }

    public function compare(Collection $debts, float $budget): array
    {
        $out = [];
        foreach (array_keys(self::STRATEGIES) as $s) {
            $out[$s] = $this->simulate($debts, $budget, $s);
        }
        // pagando só o mínimo
        $out['minimum'] = $this->simulate($debts, (float) $debts->sum('minimum_payment'), 'avalanche');

        return $out;
    }

    private function priority(array $list, string $strategy): array
    {
        $idx = array_keys(array_filter($list, fn ($d) => $d['balance'] > 0));
        usort($idx, function ($a, $b) use ($list, $strategy) {
            $A = $list[$a]; $B = $list[$b];
            return match ($strategy) {
                'avalanche' => $B['rate'] <=> $A['rate'],
                'snowball' => $A['balance'] <=> $B['balance'],
                default => [($B['overdue'] || $B['rate'] > 0.05) ? 1 : 0, $B['rate'] > 0.05 ? $B['rate'] : 0, -$B['balance']]
                    <=> [($A['overdue'] || $A['rate'] > 0.05) ? 1 : 0, $A['rate'] > 0.05 ? $A['rate'] : 0, -$A['balance']],
            };
        });

        return $idx;
    }
}
