<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\FinanceAnalytics;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->filled('mes') ? Carbon::createFromFormat('Y-m', $request->query('mes'))->startOfMonth() : now()->startOfMonth();
        $a = FinanceAnalytics::current();
        $prevMonth = $month->copy()->subMonthNoOverflow();

        $cur = $a->byCategory($month);
        $prev = $a->byCategory($prevMonth)->keyBy('name');
        $comparison = $cur->map(fn ($c) => $c + ['prev' => $prev[$c['name']]['total'] ?? 0]);

        $daily = Transaction::spending()->inMonth($month)->selectRaw('extract(day from date)::int as d, sum(amount) as total')
            ->groupBy('d')->pluck('total', 'd');
        $weekday = Transaction::spending()->where('kind', 'daily')->where('date', '>=', now()->subMonths(3))
            ->selectRaw('extract(dow from date)::int as w, sum(amount) as total')->groupBy('w')->pluck('total', 'w');
        $top = Transaction::spending()->inMonth($month)->with('category')->orderByDesc('amount')->take(10)->get();

        $history = app(\App\Services\NetWorthHistory::class);
        $history->capture($request->user(), household());

        return view('reports.index', [
            'netWorthSeries' => $history->series($request->user(), household(), 12),
            'month' => $month,
            'summary' => $a->monthSummary($month),
            'prevSummary' => $a->monthSummary($prevMonth),
            'byCategory' => $comparison,
            'incomeByCategory' => $a->byCategory($month, 'income'),
            'history' => $a->history(12),
            'rule' => $a->rule503020($month),
            'daily' => collect(range(1, $month->daysInMonth))->map(fn ($d) => (float) ($daily[$d] ?? 0)),
            'weekday' => collect(range(0, 6))->map(fn ($w) => (float) ($weekday[$w] ?? 0)),
            'top' => $top,
            'subscriptions' => $a->detectSubscriptions(),
            'projection' => $a->projection(12),
            'radar' => $a->installmentRadar(),
            'netWorth' => $a->netWorth(),
            'hourly' => $a->hourlyRate($request->user()),
        ]);
    }
}
