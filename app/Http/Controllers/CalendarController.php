<?php

namespace App\Http\Controllers;

use App\Models\CreditCard;
use App\Models\Transaction;
use App\Services\Market\BrasilApi;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request, BrasilApi $brasil)
    {
        $month = $request->filled('mes') ? Carbon::createFromFormat('Y-m', $request->query('mes'))->startOfMonth() : now()->startOfMonth();
        $items = Transaction::with('category')->whereNull('credit_card_id')->where('kind', '!=', 'daily')
            ->inMonth($month)->orderBy('date')->get()
            ->map(fn ($t) => ['day' => $t->date->day, 'label' => $t->label(), 'amount' => (float) $t->amount, 'type' => $t->type, 'paid' => $t->paid, 'icon' => $t->category?->icon ?? '•', 'id' => $t->id]);

        foreach (CreditCard::where('archived', false)->get() as $card) {
            $total = $card->invoiceTotal($month);
            if ($total > 0) {
                $due = $card->dueDateFor($month);
                $paid = ! Transaction::where('credit_card_id', $card->id)->whereDate('invoice_month', $month->toDateString())->where('paid', false)->exists();
                $items->push(['day' => $due->day, 'label' => "Fatura {$card->name}", 'amount' => $total, 'type' => 'expense', 'paid' => $paid, 'icon' => '💳', 'id' => null, 'card' => $card->id]);
            }
        }

        $holidays = collect($brasil->holidays($month->year))->filter(fn ($h) => str_starts_with($h['date'] ?? '', $month->format('Y-m')))
            ->mapWithKeys(fn ($h) => [(int) substr($h['date'], 8, 2) => $h['name']]);

        $byDay = $items->groupBy('day');
        $running = [];
        $acc = 0;
        for ($d = 1; $d <= $month->daysInMonth; $d++) {
            foreach ($byDay[$d] ?? [] as $i) {
                $acc += $i['type'] === 'income' ? $i['amount'] : -$i['amount'];
            }
            $running[$d] = $acc;
        }

        return view('calendar.index', [
            'month' => $month, 'byDay' => $byDay, 'holidays' => $holidays, 'running' => $running,
            'income' => $items->where('type', 'income')->sum('amount'), 'expense' => $items->where('type', 'expense')->sum('amount'),
            'pending' => $items->where('paid', false)->where('type', 'expense')->sum('amount'),
        ]);
    }
}
