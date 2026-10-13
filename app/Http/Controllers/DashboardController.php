<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Debt;
use App\Models\Goal;
use App\Models\Transaction;
use App\Services\CoupleSettlement;
use App\Services\FinanceAnalytics;
use App\Services\InsightEngine;
use App\Services\Market\BcbClient;
use App\Services\Market\QuoteService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, BcbClient $bcb, QuoteService $quotes)
    {
        $a = FinanceAnalytics::current();
        $household = $a->household();
        $month = now();

        $history = app(\App\Services\NetWorthHistory::class);
        $history->capture($request->user(), $household);
        $series = $history->series($request->user(), $household, 6);

        return view('dashboard', [
            'netWorthSeries' => $series,
            'household' => $household,
            'summary' => $a->monthSummary($month),
            'safe' => $a->safeToSpend(),
            'score' => $a->score(),
            'netWorth' => $a->netWorth(),
            'byCategory' => $a->byCategory($month)->take(8),
            'history' => $a->history(6),
            'projection' => $a->projection(6),
            'upcoming' => $a->upcoming(10)->take(8),
            'insights' => (new InsightEngine($a))->generate(),
            'radar' => (new \App\Services\ProblemRadar($a))->scan()->whereIn('severity', ['critical', 'warning'])->take(4),
            'installmentRadar' => $a->installmentRadar()->take(5),
            'budgets' => $a->budgets($month)->sortByDesc('pct')->take(5),
            'accounts' => Account::where('archived', false)->get(),
            'cards' => CreditCard::where('archived', false)->get(),
            'goals' => Goal::whereNull('achieved_at')->orderBy('target_date')->take(3)->get(),
            'debtTotal' => (float) Debt::where('status', '!=', 'paid')->sum('current_balance'),
            'recent' => Transaction::with(['category', 'creditCard', 'account', 'user'])->latest('date')->latest('id')->take(6)->get(),
            'hourly' => $a->hourlyRate($request->user()),
            'couple' => $household->isCouple() ? (new CoupleSettlement($household))->forMonth($month) : null,
            'indicators' => rescue(fn () => $bcb->indicators(), [], false),
            'fx' => rescue(fn () => $quotes->currencies(), [], false),
        ]);
    }
}
