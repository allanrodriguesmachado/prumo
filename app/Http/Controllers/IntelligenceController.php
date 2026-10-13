<?php

namespace App\Http\Controllers;

use App\Models\CreditCard;
use App\Services\FinanceAnalytics;
use App\Services\ProblemRadar;
use App\Services\TimeMachine;
use App\Services\WeeklyReview;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IntelligenceController extends Controller
{
    public function timeMachine(Request $request)
    {
        $result = null;
        $input = null;
        if ($request->filled('amount')) {
            $request->merge(['amount' => parse_money($request->query('amount'))]);
            $input = $request->validate([
                'type' => ['required', Rule::in(['purchase', 'recurring_expense', 'recurring_income', 'income_loss'])],
                'amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
                'installments' => ['nullable', 'integer', 'min:1', 'max:48'],
                'card_id' => ['nullable', Rule::exists('credit_cards', 'id')->where('household_id', household()->id)],
                'months' => ['nullable', 'integer', 'min:1', 'max:12'],
                'start' => ['nullable', 'date_format:Y-m'],
                'description' => ['nullable', 'string', 'max:80'],
            ]);
            if (! empty($input['start'])) {
                $input['start'] = Carbon::createFromFormat('Y-m', $input['start'])->startOfMonth()->toDateString();
            }
            $result = (new TimeMachine(FinanceAnalytics::current()))->simulate($input);
        }

        return view('intelligence.time-machine', [
            'result' => $result,
            'input' => $input,
            'cards' => CreditCard::where('archived', false)->get(),
        ]);
    }

    public function radar()
    {
        $a = FinanceAnalytics::current();

        return view('intelligence.radar', [
            'alerts' => (new ProblemRadar($a))->scan(),
            'projection' => $a->projection(6),
            'income' => $a->monthlyIncome(),
            'fixed' => $a->monthlyFixedCosts(),
        ]);
    }

    public function weekly(Request $request)
    {
        $end = $request->filled('ate') ? Carbon::parse($request->query('ate')) : now();

        return view('intelligence.weekly', ['r' => (new WeeklyReview(FinanceAnalytics::current()))->build($end)]);
    }
}
