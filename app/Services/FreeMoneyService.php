<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Debt;
use App\Models\Goal;
use App\Models\Household;
use App\Models\Recurrence;
use App\Models\Transaction;
use Carbon\Carbon;

/**
 * Dinheiro Livre: quanto o usuário realmente pode gastar até o próximo
 * recebimento, depois de separar contas a pagar, faturas, metas, plano de
 * dívidas e a meta de poupança. Saldo no banco ≠ dinheiro livre.
 */
class FreeMoneyService
{
    /** Tipos de conta considerados "dinheiro para o dia a dia". */
    public const SPENDABLE = ['checking', 'wallet', 'benefit', 'other'];

    public function __construct(private Household $household) {}

    public function periodEnd(): Carbon
    {
        $today = now()->startOfDay();
        $next = null;
        foreach (Recurrence::where('type', 'income')->where('kind', 'salary')->where('active', true)->get() as $r) {
            foreach ($r->occurrencesBetween($today->copy()->addDay(), $today->copy()->addDays(45)) as $d) {
                $next = $next ? $next->min($d) : $d;
                break;
            }
        }

        // o período vai até a véspera do próximo salário (ou fim do mês)
        return $next ? $next->copy()->subDay()->endOfDay() : now()->endOfMonth();
    }

    public function calculate(): array
    {
        $today = now()->startOfDay();
        $end = $this->periodEnd();
        $daysLeft = max(1, (int) $today->diffInDays($end->copy()->startOfDay()) + 1);

        $accounts = Account::where('archived', false)->whereIn('type', self::SPENDABLE)->get();
        $balance = $accounts->sum(fn ($a) => $a->balance());

        $incoming = (float) Transaction::earning()->where('paid', false)
            ->whereBetween('date', [$today->toDateString(), $end->toDateString()])->sum('amount');

        // contas a pagar (inclui vencidas não pagas: precisam sair do mesmo dinheiro)
        $bills = (float) Transaction::spending()->whereNull('credit_card_id')->where('paid', false)
            ->whereDate('date', '<=', $end->toDateString())->sum('amount');

        $cards = 0.0;
        foreach (CreditCard::where('archived', false)->get() as $card) {
            $months = Transaction::where('credit_card_id', $card->id)->where('paid', false)->where('type', 'expense')
                ->whereNotNull('invoice_month')->distinct()->pluck('invoice_month');
            foreach ($months as $m) {
                if ($card->dueDateFor(Carbon::parse($m))->lte($end)) {
                    $cards += max(0, $card->invoiceTotal(Carbon::parse($m)) - $this->paidPartial($card, Carbon::parse($m)));
                }
            }
        }

        $monthStart = now()->startOfMonth()->toDateString();
        $goalsPlanned = Goal::whereNull('achieved_at')->get()->sum(fn ($g) => (float) ($g->monthlyNeeded() ?? 0));
        $goalsDone = (float) Transaction::where('kind', 'goal')->where('date', '>=', $monthStart)->sum('amount');
        $goals = max(0, $goalsPlanned - $goalsDone);

        $activeDebts = Debt::where('status', '!=', 'paid')->get();
        $debtTarget = (float) ($this->household->setting('debt_budget') ?: $activeDebts->sum('minimum_payment'));
        $debtPaid = (float) Transaction::where('kind', 'debt_payment')->where('date', '>=', $monthStart)->sum('amount');
        $debtPending = (float) Transaction::where('kind', 'debt_payment')->where('paid', false)->whereDate('date', '<=', $end->toDateString())->sum('amount');
        $debts = $activeDebts->isEmpty() ? 0 : max(0, $debtTarget - $debtPaid - $debtPending);

        $analytics = new FinanceAnalytics($this->household);
        $income = max((float) Transaction::earning()->inMonth(now())->sum('amount'), $analytics->monthlyIncome());
        $savingPct = (float) $this->household->setting('saving_target_pct', 10);
        $savingTarget = $income * $savingPct / 100;
        $alreadySaved = $goalsDone + (float) Transaction::spending()->where('date', '>=', $monthStart)
            ->whereHas('category', fn ($q) => $q->where('name', 'Investimentos'))->sum('amount');
        $savings = max(0, $savingTarget - $goals - $alreadySaved);

        $free = $balance + $incoming - $bills - $cards - $goals - $debts - $savings;
        $spentToday = (float) Transaction::spending()->where('kind', 'daily')->whereDate('date', $today->toDateString())->sum('amount');
        $perDay = ($free + $spentToday) / $daysLeft;

        $lines = array_values(array_filter([
            ['label' => 'Saldo nas contas do dia a dia', 'value' => $balance, 'hint' => $accounts->count().' conta(s); poupança e investimentos ficam de fora'],
            ['label' => 'Receitas previstas até '.$end->format('d/m'), 'value' => $incoming],
            ['label' => 'Contas a pagar (inclui vencidas)', 'value' => -$bills],
            ['label' => 'Faturas de cartão que vencem no período', 'value' => -$cards],
            $goals > 0 ? ['label' => 'Reserva para metas deste mês', 'value' => -$goals] : null,
            $debts > 0 ? ['label' => 'Plano de dívidas (ainda não pago)', 'value' => -$debts] : null,
            $savings > 0 ? ['label' => "Meta de poupança ({$savingPct}% da renda)", 'value' => -$savings] : null,
        ], fn ($l) => $l && abs($l['value']) >= 0.01));

        return [
            'free' => round($free, 2),
            'per_day' => round(max(0, $perDay), 2),
            'today_left' => round(max(0, $perDay - $spentToday), 2),
            'spent_today' => round($spentToday, 2),
            'days_left' => $daysLeft,
            'period_end' => $end,
            'balance' => round($balance, 2),
            'saving_target' => round($savingTarget, 2),
            'negative' => $free < 0,
            'lines' => $lines,
            'assumptions' => [
                'Considera só o que está cadastrado no Prumo (contas, lançamentos previstos, faturas, metas e dívidas).',
                'O período vai até a véspera do próximo salário cadastrado; sem salário, até o fim do mês.',
                'Contas de poupança e investimento não entram como dinheiro para gastar.',
            ],
        ];
    }

    private function paidPartial(CreditCard $card, Carbon $month): float
    {
        return $card->invoicePaid($month);
    }
}
