<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Debt;
use App\Models\Goal;
use App\Models\Household;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Cérebro analítico do Prumo. Todas as consultas usam o escopo da carteira ativa.
 */
class FinanceAnalytics
{
    public function __construct(private Household $household) {}

    public static function current(): self
    {
        return new self(household());
    }

    public function household(): Household
    {
        return $this->household;
    }

    // ---------------------------------------------------------------- mês

    public function monthSummary(Carbon $month): array
    {
        $income = (float) Transaction::earning()->inMonth($month)->sum('amount');
        $incomePaid = (float) Transaction::earning()->inMonth($month)->where('paid', true)->sum('amount');
        $expense = (float) Transaction::spending()->inMonth($month)->sum('amount');
        $expensePaid = (float) Transaction::spending()->inMonth($month)->where(fn ($q) => $q->where('paid', true)->orWhereNotNull('credit_card_id'))->sum('amount');

        $byKind = Transaction::spending()->inMonth($month)->selectRaw('kind, sum(amount) as total')->groupBy('kind')->pluck('total', 'kind')->map(fn ($v) => (float) $v);
        $incomeByKind = Transaction::earning()->inMonth($month)->selectRaw('kind, sum(amount) as total')->groupBy('kind')->pluck('total', 'kind')->map(fn ($v) => (float) $v);

        return [
            'income' => $income,
            'income_received' => $incomePaid,
            'expense' => $expense,
            'expense_paid' => $expensePaid,
            'balance' => $income - $expense,
            'savings_rate' => $income > 0 ? ($income - $expense) / $income * 100 : 0,
            'salary' => $incomeByKind['salary'] ?? 0,
            'extra' => ($incomeByKind['extra_income'] ?? 0) + ($incomeByKind['other_income'] ?? 0),
            'fixed' => $byKind['fixed'] ?? 0,
            'installments' => $byKind['installment'] ?? 0,
            'daily' => $byKind['daily'] ?? 0,
            'debt_payments' => $byKind['debt_payment'] ?? 0,
            'goals' => $byKind['goal'] ?? 0,
        ];
    }

    public function byCategory(Carbon $month, string $type = 'expense'): Collection
    {
        $q = $type === 'expense' ? Transaction::spending() : Transaction::earning();
        $totals = $q->inMonth($month)->selectRaw('category_id, sum(amount) as total')->groupBy('category_id')->pluck('total', 'category_id');
        $cats = Category::whereIn('id', $totals->keys()->filter())->get()->keyBy('id');

        return $totals->map(function ($total, $catId) use ($cats) {
            $c = $cats[$catId] ?? null;

            return [
                'id' => $c?->id, 'name' => $c?->name ?? 'Sem categoria', 'icon' => $c?->icon ?? '❔',
                'color' => $c?->color ?? '#94a3b8', 'group' => $c?->group ?? 'wants',
                'budget' => $c?->monthly_budget ? (float) $c->monthly_budget : null, 'total' => (float) $total,
            ];
        })->sortByDesc('total')->values();
    }

    /** Orçamentos por categoria com ritmo de consumo. */
    public function budgets(Carbon $month): Collection
    {
        $spent = Transaction::spending()->inMonth($month)->selectRaw('category_id, sum(amount) as total')->groupBy('category_id')->pluck('total', 'category_id');
        $elapsed = $month->isSameMonth(now()) ? now()->day / now()->daysInMonth : ($month->isPast() ? 1 : 0);

        return Category::where('type', 'expense')->whereNotNull('monthly_budget')->where('monthly_budget', '>', 0)->orderBy('name')->get()
            ->map(function ($c) use ($spent, $elapsed) {
                $s = (float) ($spent[$c->id] ?? 0);
                $b = (float) $c->monthly_budget;
                $pct = $b > 0 ? $s / $b * 100 : 0;
                $status = $pct >= 100 ? 'over' : ($elapsed > 0 && $pct > $elapsed * 100 + 10 ? 'fast' : 'ok');

                return ['category' => $c, 'spent' => $s, 'budget' => $b, 'pct' => $pct, 'status' => $status, 'left' => $b - $s];
            });
    }

    /** Regra 50/30/20 aplicada ao mês. */
    public function rule503020(Carbon $month): array
    {
        $income = (float) Transaction::earning()->inMonth($month)->sum('amount');
        $groups = Transaction::spending()->inMonth($month)
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->selectRaw("coalesce(categories.\"group\", 'wants') as grp, sum(transactions.amount) as total")
            ->groupBy('grp')->pluck('total', 'grp');
        $out = [];
        foreach (['needs' => 50, 'wants' => 30, 'savings' => 20] as $g => $ideal) {
            $v = (float) ($groups[$g] ?? 0);
            $out[$g] = ['total' => $v, 'pct' => $income > 0 ? $v / $income * 100 : 0, 'ideal' => $ideal];
        }
        $out['savings']['total'] += max(0, $income - array_sum(array_column($out, 'total')));
        $out['savings']['pct'] = $income > 0 ? $out['savings']['total'] / $income * 100 : 0;

        return $out;
    }

    // ---------------------------------------------------------------- saldos

    public function totalBalance(): float
    {
        return Account::where('archived', false)->get()->sum(fn ($a) => $a->balance());
    }

    public function netWorth(): array
    {
        $cash = $this->totalBalance();
        $invest = Asset::with('operations')->get()->sum(fn ($a) => $a->position()['value']);
        $goals = (float) Goal::sum('current_amount');
        $debts = (float) Debt::where('status', '!=', 'paid')->sum('current_balance');
        $cardOpen = (float) Transaction::whereNotNull('credit_card_id')->where('type', 'expense')->where('paid', false)->sum('amount');

        return [
            'cash' => $cash, 'investments' => $invest, 'goals' => $goals, 'debts' => $debts, 'cards' => $cardOpen,
            'total' => $cash + $invest + $goals - $debts - $cardOpen,
        ];
    }

    /** Renda mensal média (recorrências + média de rendas extras dos últimos 3 meses). */
    public function monthlyIncome(?User $user = null): float
    {
        $rec = Recurrence::where('type', 'income')->where('active', true)
            ->when($user, fn ($q) => $q->where('user_id', $user->id))->get()->sum(fn ($r) => $r->monthlyAmount());
        $extra = (float) Transaction::earning()->whereNull('recurrence_id')
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->whereBetween('date', [now()->subMonths(3)->startOfMonth(), now()->subMonth()->endOfMonth()])->sum('amount') / 3;

        if ($rec <= 0) {
            $avg = (float) Transaction::earning()->when($user, fn ($q) => $q->where('user_id', $user->id))
                ->whereBetween('date', [now()->subMonths(3)->startOfMonth(), now()->endOfMonth()])->sum('amount') / 3;

            return round($avg, 2);
        }

        return round($rec + $extra, 2);
    }

    public function monthlyFixedCosts(): float
    {
        return round(Recurrence::where('type', 'expense')->where('active', true)->get()->sum(fn ($r) => $r->monthlyAmount()), 2);
    }

    public function averageMonthlySpending(int $months = 3): float
    {
        $total = (float) Transaction::spending()->whereBetween('date', [now()->subMonths($months)->startOfMonth(), now()->subMonth()->endOfMonth()])->sum('amount');
        $v = $total / $months;
        if ($v <= 0) {
            $v = (float) Transaction::spending()->inMonth(now())->sum('amount');
        }

        return round($v, 2);
    }

    // ---------------------------------------------------------------- diferenciais

    /**
     * "Quanto posso gastar hoje?" — divide o que sobra do mês (renda prevista
     * menos tudo que já está comprometido) pelos dias restantes.
     */
    public function safeToSpend(): array
    {
        return (new FreeMoneyService($this->household))->calculate();
    }

    /** Valor/hora do trabalho para mostrar "isso custou X horas da sua vida". */
    public function hourlyRate(User $user): float
    {
        $income = Recurrence::where('type', 'income')->where('kind', 'salary')->where('active', true)->where('user_id', $user->id)->get()->sum(fn ($r) => $r->monthlyAmount());
        if ($income <= 0) {
            $income = $this->monthlyIncome($user);
        }

        return $income > 0 ? $income / max(1, $user->work_hours_per_month) : 0;
    }

    /** Projeção de fluxo de caixa mês a mês, com recorrências e parcelas futuras. */
    /**
     * @param  array<string, array{income?: float, expense?: float}>  $extra  ajustes hipotéticos por mês ('Y-m'), usados pela Máquina do Tempo
     */
    public function projection(int $months = 12, array $extra = []): array
    {
        $balance = $this->totalBalance();
        $avgDaily = (float) Transaction::spending()->where('kind', 'daily')
            ->whereBetween('date', [now()->subMonths(3)->startOfMonth(), now()->subMonth()->endOfMonth()])->sum('amount') / 3;
        $recurrences = Recurrence::where('active', true)->get();
        $debtsMin = (float) Debt::where('status', '!=', 'paid')->sum('minimum_payment');
        $rows = [];

        for ($i = 0; $i < $months; $i++) {
            $m = now()->startOfMonth()->addMonthsNoOverflow($i);
            $start = $i === 0 ? now()->startOfDay() : $m->copy();
            $end = $m->copy()->endOfMonth();

            // lançamentos já existentes ainda não pagos/realizados
            $pendIncome = (float) Transaction::earning()->where('paid', false)->whereBetween('date', [$start->toDateString(), $end->toDateString()])->sum('amount');
            $pendExpense = (float) Transaction::spending()->whereNull('credit_card_id')->where('paid', false)->whereBetween('date', [$start->toDateString(), $end->toDateString()])->sum('amount');
            $cards = (float) Transaction::whereNotNull('credit_card_id')->where('type', 'expense')->where('paid', false)->whereDate('invoice_month', $m->toDateString())->sum('amount');
            $installments = (float) Transaction::where('kind', 'installment')->whereBetween('date', [$m->toDateString(), $end->toDateString()])->sum('amount');

            // recorrências ainda não materializadas
            $recIncome = 0; $recExpense = 0;
            foreach ($recurrences as $r) {
                $genUntil = $r->generated_until ? Carbon::parse($r->generated_until) : null;
                foreach ($r->occurrencesBetween($start, $end) as $d) {
                    if ($genUntil && $d->lte($genUntil)) {
                        continue;
                    }
                    $r->type === 'income' ? $recIncome += (float) $r->amount : $recExpense += (float) $r->amount;
                }
            }

            $daily = $i === 0 ? $avgDaily * (now()->daysInMonth - now()->day) / now()->daysInMonth : $avgDaily;
            $adj = $extra[$m->format('Y-m')] ?? [];
            $in = $pendIncome + $recIncome + (float) ($adj['income'] ?? 0);
            $out = $pendExpense + $cards + $recExpense + $daily + ($i === 0 ? 0 : $debtsMin) + (float) ($adj['expense'] ?? 0);
            $balance += $in - $out;
            $rows[] = [
                'month' => $m, 'label' => ucfirst(month_name($m, true)).'/'.$m->format('y'),
                'income' => round($in, 2), 'expense' => round($out, 2), 'installments' => round($installments, 2),
                'cards' => round($cards, 2), 'balance' => round($balance, 2), 'committed' => round($recExpense + $pendExpense + $cards, 2),
            ];
        }

        return $rows;
    }

    /** Radar de parcelas: quando cada parcelamento termina e quanto "libera" da renda. */
    public function installmentRadar(): Collection
    {
        return Transaction::where('kind', 'installment')->whereNotNull('installment_group')
            ->where('paid', false)
            ->selectRaw('installment_group, max(description) as description, max(installment_total) as total_n, count(*) as remaining, max(date) as last_date, min(amount) as amount, sum(amount) as remaining_value')
            ->groupBy('installment_group')->orderBy('last_date')->get()
            ->map(fn ($r) => [
                'description' => $r->description, 'total' => (int) $r->total_n, 'remaining' => (int) $r->remaining,
                'last_date' => Carbon::parse($r->last_date), 'amount' => (float) $r->amount, 'remaining_value' => (float) $r->remaining_value,
            ]);
    }

    /** Lançamentos que se repetem e talvez você nem lembre (assinaturas). */
    public function detectSubscriptions(): Collection
    {
        $rows = Transaction::spending()->whereNull('recurrence_id')->where('kind', '!=', 'installment')
            ->where('date', '>=', now()->subMonths(4)->startOfMonth())
            ->get(['description', 'amount', 'date']);

        return $rows->groupBy(fn ($t) => \App\Services\Import\Categorizer::ruleKey($t->description).'|'.round((float) $t->amount))
            ->filter(fn ($g) => $g->pluck('date')->map(fn ($d) => $d->format('Y-m'))->unique()->count() >= 3)
            ->map(fn ($g) => ['description' => $g->first()->description, 'amount' => (float) $g->first()->amount, 'months' => $g->count(), 'yearly' => (float) $g->first()->amount * 12])
            ->sortByDesc('amount')->values();
    }

    /** Histórico de receitas x despesas. */
    public function history(int $months = 6): array
    {
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $m = now()->startOfMonth()->subMonthsNoOverflow($i);
            $out[] = [
                'label' => ucfirst(month_name($m, true)),
                'income' => (float) Transaction::earning()->inMonth($m)->sum('amount'),
                'expense' => (float) Transaction::spending()->inMonth($m)->sum('amount'),
            ];
        }

        return $out;
    }

    /** Próximos vencimentos (contas pendentes e faturas). */
    public function upcoming(int $days = 10): Collection
    {
        $items = Transaction::with('category')->whereNull('credit_card_id')->where('paid', false)
            ->whereBetween('date', [now()->subDays(30)->toDateString(), now()->addDays($days)->toDateString()])
            ->orderBy('date')->get()
            ->map(fn ($t) => ['type' => $t->type, 'label' => $t->label(), 'date' => $t->date, 'amount' => (float) $t->amount, 'overdue' => $t->date->lt(now()->startOfDay()), 'id' => $t->id, 'icon' => $t->category?->icon ?? '•']);

        foreach (CreditCard::where('archived', false)->get() as $card) {
            foreach ([now()->startOfMonth(), now()->startOfMonth()->addMonth()] as $m) {
                $due = $card->dueDateFor($m);
                $open = (float) Transaction::where('credit_card_id', $card->id)->whereDate('invoice_month', $m->toDateString())->where('paid', false)->where('type', 'expense')->sum('amount');
                if ($open > 0 && $due->lte(now()->addDays($days)) && $due->gte(now()->subDays(30))) {
                    $items->push(['type' => 'expense', 'label' => "Fatura {$card->name}", 'date' => $due, 'amount' => $open, 'overdue' => $due->lt(now()->startOfDay()), 'id' => null, 'icon' => '💳', 'card' => $card->id, 'invoice' => $m->toDateString()]);
                }
            }
        }

        return $items->sortBy('date')->values();
    }

    // ---------------------------------------------------------------- score

    /**
     * Prumo Score (0–1000): nota de saúde financeira com 6 pilares.
     */
    public function score(): array
    {
        $income = $this->monthlyIncome();
        $spend = max($this->averageMonthlySpending(), (float) Transaction::spending()->inMonth(now())->sum('amount'));
        $debts = Debt::where('status', '!=', 'paid')->get();
        $debtTotal = (float) $debts->sum('current_balance');
        $debtMin = (float) $debts->sum('minimum_payment');
        $emergency = (float) Goal::where('type', 'emergency')->sum('current_amount') + Account::where('type', 'savings')->get()->sum(fn ($a) => max(0, $a->balance()));
        $investments = Asset::with('operations')->get()->sum(fn ($a) => $a->position()['value']);
        $essential = $this->monthlyFixedCosts() ?: $spend * 0.6;
        $budgetCount = Category::whereNotNull('monthly_budget')->count();
        $overdue = $debts->where('is_overdue', true)->count();

        $pillars = [];
        // 1. Taxa de poupança (até 250)
        $savingRate = $income > 0 ? ($income - $spend) / $income : -1;
        $pillars['saving'] = ['label' => 'Sobra no fim do mês', 'max' => 250, 'value' => (int) round(max(0, min(1, ($savingRate + 0.05) / 0.25)) * 250),
            'hint' => $income > 0 ? 'Você guarda '.pct(max(-100, $savingRate * 100)).' da renda. Meta: 20%.' : 'Cadastre sua renda para calcular.'];
        // 2. Reserva de emergência (até 200)
        $months = $essential > 0 ? $emergency / $essential : 0;
        $pillars['emergency'] = ['label' => 'Reserva de emergência', 'max' => 200, 'value' => (int) round(min(1, $months / 6) * 200),
            'hint' => 'Reserva cobre '.num($months, 1).' meses de gastos essenciais. Meta: 6.'];
        // 3. Endividamento (até 250)
        $dti = $income > 0 ? $debtMin / $income : ($debtTotal > 0 ? 1 : 0);
        $debtScore = $debtTotal <= 0 ? 250 : (int) round(max(0, 1 - $dti / 0.35) * 200) + ($overdue ? 0 : 50);
        $pillars['debt'] = ['label' => 'Dívidas sob controle', 'max' => 250, 'value' => min(250, $debtScore),
            'hint' => $debtTotal > 0 ? 'Parcelas de dívida usam '.pct($dti * 100).' da renda'.($overdue ? " e há {$overdue} em atraso." : '.') : 'Sem dívidas ativas. 👏'];
        // 4. Investimentos (até 150)
        $pillars['invest'] = ['label' => 'Patrimônio investido', 'max' => 150, 'value' => (int) round(min(1, $income > 0 ? $investments / ($income * 12) : 0) * 150),
            'hint' => 'Investimentos equivalem a '.num($income > 0 ? $investments / $income : 0, 1).' meses de renda. Meta: 12+.'];
        // 5. Planejamento (até 100)
        $plan = min(50, $budgetCount * 10) + (Goal::count() > 0 ? 25 : 0) + (Recurrence::count() > 0 ? 25 : 0);
        $pillars['plan'] = ['label' => 'Planejamento', 'max' => 100, 'value' => $plan, 'hint' => "{$budgetCount} orçamentos definidos, ".Goal::count().' metas.'];
        // 6. Cartão de crédito (até 50)
        $limit = (float) CreditCard::sum('limit');
        $used = (float) Transaction::whereNotNull('credit_card_id')->where('type', 'expense')->where('paid', false)->sum('amount');
        $util = $limit > 0 ? $used / $limit : 0;
        $pillars['card'] = ['label' => 'Uso do cartão', 'max' => 50, 'value' => (int) round(max(0, 1 - max(0, $util - 0.3) / 0.7) * 50),
            'hint' => $limit > 0 ? 'Usando '.pct($util * 100).' do limite. Ideal: até 30%.' : 'Nenhum cartão cadastrado.'];

        $total = array_sum(array_column($pillars, 'value'));
        $level = match (true) {
            $total >= 850 => ['Excelente', 'emerald'],
            $total >= 700 => ['Muito bom', 'teal'],
            $total >= 500 => ['No caminho', 'amber'],
            $total >= 300 => ['Atenção', 'orange'],
            default => ['Hora de agir', 'rose'],
        };

        return ['total' => $total, 'level' => $level[0], 'color' => $level[1], 'pillars' => $pillars];
    }
}
