<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use App\Services\FinanceAnalytics;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->filled('mes') ? Carbon::createFromFormat('Y-m', $request->query('mes'))->startOfMonth() : now()->startOfMonth();
        $a = FinanceAnalytics::current();
        $spent = Transaction::spending()->inMonth($month)->selectRaw('category_id, sum(amount) as total')->groupBy('category_id')->pluck('total', 'category_id');
        $avg = Transaction::spending()->whereBetween('date', [now()->subMonths(3)->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->selectRaw('category_id, sum(amount)/3 as avg')->groupBy('category_id')->pluck('avg', 'category_id');

        return view('budgets.index', [
            'month' => $month,
            'categories' => Category::where('type', 'expense')->where('archived', false)->orderBy('group')->orderBy('name')->get(),
            'spent' => $spent,
            'avg' => $avg,
            'income' => $a->monthlyIncome(),
            'rule' => $a->rule503020($month),
            'elapsed' => $month->isSameMonth(now()) ? now()->day / now()->daysInMonth * 100 : ($month->isPast() ? 100 : 0),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['budget' => ['array'], 'budget.*' => ['nullable']]);
        foreach ($data['budget'] ?? [] as $id => $value) {
            $v = $value === null || $value === '' ? null : parse_money($value);
            Category::whereKey($id)->update(['monthly_budget' => $v > 0 ? $v : null]);
        }
        if ($request->filled('saving_target_pct')) {
            household()->putSetting('saving_target_pct', max(0, min(80, (int) $request->input('saving_target_pct'))));
        }

        return back()->with('success', 'Orçamentos salvos.');
    }

    /** Sugere orçamentos: média dos últimos 3 meses, com corte de 10% no estilo de vida. */
    public function suggest()
    {
        $avg = Transaction::spending()->whereBetween('date', [now()->subMonths(3)->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->selectRaw('category_id, sum(amount)/3 as avg')->groupBy('category_id')->pluck('avg', 'category_id');
        $n = 0;
        foreach (Category::where('type', 'expense')->whereIn('id', $avg->keys()->filter())->get() as $c) {
            $v = (float) $avg[$c->id] * ($c->group === 'wants' ? 0.9 : 1.0);
            $c->update(['monthly_budget' => ceil($v / 10) * 10]);
            $n++;
        }

        return back()->with('success', $n ? "{$n} orçamentos sugeridos com base nos últimos 3 meses (estilo de vida com 10% de corte)." : 'Ainda não há histórico suficiente para sugerir orçamentos.');
    }
}
