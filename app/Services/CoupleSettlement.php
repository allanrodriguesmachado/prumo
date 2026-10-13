<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Transaction;
use Carbon\Carbon;

/**
 * Acerto de contas do casal: calcula quanto cada um deveria pagar das
 * despesas compartilhadas (igual, proporcional à renda ou percentual
 * combinado) e quem precisa transferir quanto para quem.
 */
class CoupleSettlement
{
    public function __construct(private Household $household) {}

    public function forMonth(Carbon $month): ?array
    {
        $members = $this->household->members()->get();
        if ($members->count() < 2) {
            return null;
        }
        [$a, $b] = [$members[0], $members[1]];
        $analytics = new FinanceAnalytics($this->household);

        $shares = match ($this->household->split_mode) {
            'equal' => [$a->id => 50.0, $b->id => 50.0],
            'custom' => [
                $this->household->owner_id => (float) $this->household->split_owner_percent,
                $members->firstWhere('id', '!=', $this->household->owner_id)->id => 100 - (float) $this->household->split_owner_percent,
            ],
            default => $this->proportional($analytics, $a, $b),
        };

        $shared = Transaction::spending()->inMonth($month)->settleable()->with('user')->get();
        $total = (float) $shared->sum('amount');
        $rows = [];
        foreach ([$a, $b] as $m) {
            $paid = (float) $shared->where('user_id', $m->id)->sum('amount');
            $fair = $total * $shares[$m->id] / 100;
            $rows[$m->id] = [
                'user' => $m, 'share_pct' => $shares[$m->id], 'paid' => $paid, 'fair' => $fair, 'diff' => $paid - $fair,
                'personal' => (float) Transaction::spending()->inMonth($month)->where('is_shared', false)->where('user_id', $m->id)->sum('amount'),
                'income' => (float) Transaction::earning()->inMonth($month)->where('user_id', $m->id)->sum('amount'),
            ];
        }

        $debtor = $rows[$a->id]['diff'] < 0 ? $a : $b;
        $creditor = $debtor->id === $a->id ? $b : $a;
        $amount = abs($rows[$debtor->id]['diff']);

        return [
            'total_shared' => $total,
            'members' => $rows,
            'transfer' => $amount >= 0.01 ? ['from' => $debtor, 'to' => $creditor, 'amount' => round($amount, 2)] : null,
            'mode' => $this->household->split_mode,
            'items' => $shared->sortByDesc('date')->values(),
            'pending' => Transaction::with('user')->where('approval_status', 'pending')->orderBy('date')->get(),
        ];
    }

    private function proportional(FinanceAnalytics $analytics, $a, $b): array
    {
        $ia = $analytics->monthlyIncome($a);
        $ib = $analytics->monthlyIncome($b);
        if ($ia + $ib <= 0) {
            return [$a->id => 50.0, $b->id => 50.0];
        }

        return [$a->id => round($ia / ($ia + $ib) * 100, 1), $b->id => round($ib / ($ia + $ib) * 100, 1)];
    }
}
