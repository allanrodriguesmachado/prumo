<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Household;
use App\Models\NetWorthSnapshot;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Evolução patrimonial: fotos diárias do patrimônio de cada pessoa.
 * Sem histórico, reconstrói estimativas mensais do saldo em contas a
 * partir dos lançamentos (marcadas como estimadas).
 */
class NetWorthHistory
{
    /** Registra a foto de hoje para o usuário na carteira (com a visibilidade dele). */
    public function capture(User $user, Household $household): NetWorthSnapshot
    {
        return $this->asUser($user, $household, function () use ($user, $household) {
            $nw = (new FinanceAnalytics($household))->netWorth();

            return NetWorthSnapshot::updateOrCreate(
                ['household_id' => $household->id, 'user_id' => $user->id, 'date' => now()->toDateString()],
                ['cash' => $nw['cash'], 'investments' => $nw['investments'], 'goals' => $nw['goals'], 'debts' => $nw['debts'], 'cards' => $nw['cards'], 'total' => $nw['total'], 'estimated' => false],
            );
        });
    }

    /** Reconstrói o fim de cada mês passado (só contas e faturas) quando ainda não há fotos. */
    public function backfill(User $user, Household $household, int $months = 12): int
    {
        return $this->asUser($user, $household, function () use ($user, $household, $months) {
            $first = Transaction::min('date');
            if (! $first) {
                return 0;
            }
            $n = 0;
            $accounts = Account::all();
            for ($i = $months; $i >= 1; $i--) {
                $end = now()->startOfMonth()->subMonthsNoOverflow($i)->endOfMonth();
                if ($end->lt(Carbon::parse($first)->startOfMonth())) {
                    continue;
                }
                $exists = NetWorthSnapshot::where(['household_id' => $household->id, 'user_id' => $user->id])->whereDate('date', $end->toDateString())->exists();
                if ($exists) {
                    continue;
                }
                $cash = $accounts->sum(fn ($a) => $a->balance($end->toDateString()));
                // faturas de cartão em aberto naquela data: compras até a data, cuja fatura vence depois
                $cards = (float) Transaction::whereNotNull('credit_card_id')->where('type', 'expense')
                    ->whereDate('date', '<=', $end->toDateString())->whereDate('invoice_month', '>', $end->copy()->startOfMonth()->toDateString())->sum('amount');
                NetWorthSnapshot::create([
                    'household_id' => $household->id, 'user_id' => $user->id, 'date' => $end->toDateString(),
                    'cash' => $cash, 'investments' => 0, 'goals' => 0, 'debts' => 0, 'cards' => $cards, 'total' => $cash - $cards, 'estimated' => true,
                ]);
                $n++;
            }

            return $n;
        });
    }

    /** Série mensal (último registro de cada mês) para gráficos. */
    public function series(User $user, Household $household, int $months = 12): Collection
    {
        return NetWorthSnapshot::where('household_id', $household->id)->where('user_id', $user->id)
            ->where('date', '>=', now()->subMonths($months)->startOfMonth())->orderBy('date')->get()
            ->groupBy(fn ($s) => $s->date->format('Y-m'))->map->last()->values();
    }

    private function asUser(User $user, Household $household, callable $fn): mixed
    {
        $previous = Auth::user();
        $user->setAttribute('current_household_id', $household->id);
        Auth::setUser($user);
        try {
            return $fn();
        } finally {
            if ($previous) {
                Auth::setUser($previous);
            }
        }
    }
}
