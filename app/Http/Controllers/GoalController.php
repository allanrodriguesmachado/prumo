<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Goal;
use App\Services\FinanceAnalytics;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GoalController extends Controller
{
    public function index()
    {
        $a = FinanceAnalytics::current();

        $goals = Goal::with('user')->orderByRaw('achieved_at is not null')->orderBy('target_date')->get();

        return view('goals.index', [
            'goals' => $goals,
            'plan' => (new \App\Services\GoalPlanner($a))->evaluate($goals),
            'accounts' => Account::where('archived', false)->get(),
            'emergencyTarget' => round(max($a->monthlyFixedCosts(), $a->averageMonthlySpending() * 0.7) * 6, -2),
        ]);
    }

    public function create(Request $request)
    {
        $type = $request->query('tipo', 'dream');
        $a = FinanceAnalytics::current();

        return view('goals.form', ['capacity' => (new \App\Services\GoalPlanner($a))->monthlyCapacity(), 'goal' => new Goal([
            'type' => $type,
            'icon' => Goal::TYPES[$type][1] ?? '🎯',
            'name' => $type === 'emergency' ? 'Reserva de emergência' : '',
            'target_amount' => $type === 'emergency' ? round(max($a->monthlyFixedCosts(), $a->averageMonthlySpending() * 0.7) * 6, -2) : null,
            'is_shared' => household()->isCouple(),
        ])]);
    }

    public function store(Request $request)
    {
        Goal::create($this->validated($request));

        return redirect()->route('goals.index')->with('success', 'Meta criada! 🎯');
    }

    public function edit(Goal $goal)
    {
        return view('goals.form', ['goal' => $goal, 'capacity' => (new \App\Services\GoalPlanner(FinanceAnalytics::current()))->monthlyCapacity()]);
    }

    public function update(Request $request, Goal $goal)
    {
        $goal->update($this->validated($request));

        return redirect()->route('goals.index')->with('success', 'Meta atualizada.');
    }

    public function destroy(Goal $goal)
    {
        $goal->delete();

        return redirect()->route('goals.index')->with('success', 'Meta removida.');
    }

    public function contribute(Request $request, Goal $goal, TransactionService $service)
    {
        $request->merge(['amount' => parse_money($request->input('amount'))]);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0'],
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where('household_id', household()->id)],
            'withdraw' => ['nullable', 'boolean'],
        ]);
        $withdraw = $request->boolean('withdraw');
        $service->create([
            'type' => $withdraw ? 'income' : 'expense',
            'kind' => $withdraw ? 'other_income' : 'goal',
            'description' => ($withdraw ? 'Resgate da meta ' : 'Aporte na meta ').$goal->name,
            'amount' => abs($data['amount']), 'date' => now()->toDateString(), 'paid' => true,
            'account_id' => $data['account_id'] ?? null, 'goal_id' => $withdraw ? null : $goal->id,
            'category_id' => $withdraw ? Category::where('name', 'Outras receitas')->value('id') : Category::where('name', 'Metas e reservas')->value('id'),
            'is_shared' => $goal->is_shared,
        ]);
        if ($withdraw) {
            $goal->update(['current_amount' => max(0, (float) $goal->current_amount - abs($data['amount'])), 'achieved_at' => null]);
        }
        $goal->refresh();

        return back()->with('success', $goal->achieved_at ? "🏆 Meta \"{$goal->name}\" alcançada! Parabéns!" : ($withdraw ? 'Resgate registrado.' : 'Aporte registrado! Faltam '.brl($goal->remaining()).'.'));
    }

    private function validated(Request $request): array
    {
        foreach (['target_amount', 'current_amount'] as $f) {
            $request->merge([$f => parse_money($request->input($f, 0))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(array_keys(Goal::TYPES))],
            'icon' => ['required', 'string', 'max:16'],
            'target_amount' => ['required', 'numeric', 'gt:0'],
            'current_amount' => ['nullable', 'numeric', 'min:0'],
            'target_date' => ['nullable', 'date'],
        ], [], ['name' => 'nome', 'target_amount' => 'valor da meta']);
        $data['is_private'] = $request->boolean('is_private');
        $data['is_shared'] = ! $data['is_private'] && $request->boolean('is_shared');

        return $data;
    }
}
