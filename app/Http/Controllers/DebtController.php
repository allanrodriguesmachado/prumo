<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Debt;
use App\Services\DebtPlanner;
use App\Services\FinanceAnalytics;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DebtController extends Controller
{
    public function index(Request $request, DebtPlanner $planner)
    {
        $household = household();
        $a = FinanceAnalytics::current();
        $active = Debt::where('status', '!=', 'paid')->orderByDesc('is_overdue')->orderByDesc('monthly_interest_rate')->get();
        $paid = Debt::where('status', 'paid')->latest('paid_off_at')->get();

        $income = $a->monthlyIncome();
        $fixed = $a->monthlyFixedCosts();
        $minTotal = (float) $active->sum('minimum_payment');
        // sugestão: o que sobra da renda após gastos fixos (exceto dívidas), limitado a 35% da renda
        $suggested = max($minTotal, min(max(0, $income - $fixed - $a->averageMonthlySpending() * 0.3), $income * 0.35));
        $budget = (float) ($household->setting('debt_budget') ?: round($suggested, -1));
        $strategy = $household->setting('debt_strategy', 'prumo');

        $comparison = $active->isNotEmpty() ? $planner->compare($active, $budget) : [];

        return view('debts.index', [
            'active' => $active,
            'paid' => $paid,
            'budget' => $budget,
            'suggested' => round($suggested, -1),
            'strategy' => $strategy,
            'plan' => $comparison[$strategy] ?? null,
            'comparison' => $comparison,
            'total' => (float) $active->sum('current_balance'),
            'monthlyInterest' => $active->sum(fn ($d) => $d->monthlyInterestCost()),
            'minTotal' => $minTotal,
            'income' => $income,
            'rescue' => $active->where('is_overdue', true)->isNotEmpty() || ($income > 0 && $minTotal / $income > 0.35),
            'essentials' => $a->monthlyFixedCosts(),
            'accounts' => Account::where('archived', false)->get(),
            'snapshots' => \App\Models\DebtSnapshot::where('household_id', $household->id)->orderBy('date')->get(),
            // o plano nunca deve tirar dinheiro das despesas essenciais
            'essentialsAtRisk' => $income > 0 && $budget > max(0, $income - $a->monthlyFixedCosts()),
            'maxSafeBudget' => max(0, $income - $a->monthlyFixedCosts()),
        ]);
    }

    public function create()
    {
        return view('debts.form', ['debt' => new Debt(['type' => 'personal_loan', 'is_shared' => false])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['original_amount'] = $data['original_amount'] ?? $data['current_balance'];
        Debt::create($data);

        return redirect()->route('debts.index')->with('success', 'Dívida cadastrada. Veja abaixo seu plano de saída atualizado.');
    }

    public function edit(Debt $debt)
    {
        return view('debts.form', ['debt' => $debt]);
    }

    public function update(Request $request, Debt $debt)
    {
        $data = $this->validated($request);
        $data['original_amount'] = $data['original_amount'] ?? $debt->original_amount;
        $debt->update($data);

        return redirect()->route('debts.index')->with('success', 'Dívida atualizada.');
    }

    public function destroy(Debt $debt)
    {
        $debt->delete();

        return redirect()->route('debts.index')->with('success', 'Dívida removida.');
    }

    public function plan(Request $request)
    {
        $data = $request->validate([
            'strategy' => ['required', Rule::in(array_keys(DebtPlanner::STRATEGIES))],
            'budget' => ['required'],
        ]);
        household()->putSetting('debt_strategy', $data['strategy']);
        household()->putSetting('debt_budget', parse_money($data['budget']));

        return back()->with('success', 'Plano atualizado.');
    }

    public function pay(Request $request, Debt $debt, TransactionService $service)
    {
        $request->merge(['amount' => parse_money($request->input('amount'))]);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where('household_id', household()->id)],
        ]);
        $service->create([
            'type' => 'expense', 'kind' => 'debt_payment', 'description' => "Pagamento {$debt->creditor}",
            'amount' => $data['amount'], 'date' => $data['date'], 'paid' => true, 'account_id' => $data['account_id'] ?? null,
            'category_id' => \App\Models\Category::where('name', 'Dívidas e juros')->value('id'), 'debt_id' => $debt->id, 'is_shared' => $debt->is_shared,
        ]);
        $debt->refresh();

        return back()->with('success', $debt->status === 'paid' ? "🎉 Dívida com {$debt->creditor} QUITADA! Que conquista!" : 'Pagamento registrado. Saldo atual: '.brl($debt->current_balance));
    }

    /** Roteiro de negociação personalizado (sem IA externa). */
    public function negotiation(Debt $debt)
    {
        $a = FinanceAnalytics::current();
        $income = $a->monthlyIncome();
        $capacity = max(0, $income - $a->monthlyFixedCosts() - $a->averageMonthlySpending() * 0.3);
        $balance = (float) $debt->current_balance;
        $discount = $debt->is_overdue ? 0.5 : 0.15;

        return view('debts.negotiation', [
            'debt' => $debt,
            'offerCash' => round($balance * (1 - $discount), -1),
            'offerCashMax' => round($balance * (1 - $discount / 2), -1),
            'installment' => round(min(max($capacity * 0.6, (float) $debt->minimum_payment), $balance), -1),
            'capacity' => $capacity,
            'income' => $income,
        ]);
    }

    private function validated(Request $request): array
    {
        foreach (['original_amount', 'current_balance', 'minimum_payment'] as $f) {
            if ($request->filled($f)) {
                $request->merge([$f => parse_money($request->input($f))]);
            }
        }
        $request->merge(['monthly_interest_rate' => $request->filled('monthly_interest_rate') ? parse_money($request->input('monthly_interest_rate')) : (Debt::TYPES[$request->input('type')][1] ?? 3)]);
        $data = $request->validate([
            'creditor' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(Debt::TYPES))],
            'original_amount' => ['nullable', 'numeric', 'min:0'],
            'current_balance' => ['required', 'numeric', 'min:0'],
            'monthly_interest_rate' => ['required', 'numeric', 'min:0', 'max:50'],
            'minimum_payment' => ['nullable', 'numeric', 'min:0'],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'status' => ['nullable', Rule::in(['active', 'negotiating', 'paid'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['creditor' => 'credor', 'current_balance' => 'saldo devedor', 'monthly_interest_rate' => 'juros ao mês']);
        $data['minimum_payment'] = $data['minimum_payment'] ?? 0;
        $data['status'] = $data['status'] ?? 'active';
        $data['is_overdue'] = $request->boolean('is_overdue');
        $data['is_private'] = $request->boolean('is_private');
        $data['is_shared'] = ! $data['is_private'] && $request->boolean('is_shared');

        return $data;
    }
}
