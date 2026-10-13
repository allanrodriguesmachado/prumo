<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Transaction;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CreditCardController extends Controller
{
    public function index()
    {
        $cards = CreditCard::with(['user', 'account'])->orderBy('archived')->orderBy('name')->get();
        $current = $cards->mapWithKeys(fn ($c) => [$c->id => $c->invoiceMonthFor(now())]);

        return view('cards.index', [
            'cards' => $cards,
            'current' => $current,
            'totals' => $cards->mapWithKeys(fn ($c) => [$c->id => $c->invoiceTotal($current[$c->id])]),
            'used' => $cards->mapWithKeys(fn ($c) => [$c->id => $c->usedLimit()]),
        ]);
    }

    public function create()
    {
        return view('cards.form', ['card' => new CreditCard(['closing_day' => 1, 'due_day' => 10, 'color' => '#7c3aed', 'is_shared' => household()->isCouple()]), 'accounts' => Account::where('archived', false)->get()]);
    }

    public function store(Request $request)
    {
        CreditCard::create($this->validated($request));

        return redirect()->route('cards.index')->with('success', 'Cartão cadastrado.');
    }

    public function edit(CreditCard $card)
    {
        return view('cards.form', ['card' => $card, 'accounts' => Account::where('archived', false)->get()]);
    }

    public function update(Request $request, CreditCard $card)
    {
        $card->update($this->validated($request));

        return redirect()->route('cards.index')->with('success', 'Cartão atualizado.');
    }

    public function destroy(CreditCard $card)
    {
        if ($card->transactions()->exists()) {
            $card->update(['archived' => true]);

            return back()->with('success', 'O cartão tem lançamentos e foi arquivado.');
        }
        $card->delete();

        return back()->with('success', 'Cartão excluído.');
    }

    public function invoice(Request $request, CreditCard $card)
    {
        $month = $request->filled('mes') ? Carbon::createFromFormat('Y-m', $request->query('mes'))->startOfMonth() : $card->invoiceMonthFor(now());
        $items = Transaction::with(['category', 'user'])->where('credit_card_id', $card->id)
            ->whereDate('invoice_month', $month->toDateString())->orderBy('date')->get();

        return view('cards.invoice', [
            'card' => $card,
            'month' => $month,
            'items' => $items,
            'total' => $card->invoiceTotal($month),
            'paidAmount' => $card->invoicePaid($month),
            'remaining' => $card->invoiceRemaining($month),
            'paid' => $items->isNotEmpty() && $items->every->paid,
            'due' => $card->dueDateFor($month),
            'closing' => $month->copy()->subMonthsNoOverflow($card->due_day <= $card->closing_day ? 1 : 0)->day(min($card->closing_day, $month->daysInMonth)),
            'accounts' => Account::where('archived', false)->get(),
            'byCategory' => $items->where('type', 'expense')->groupBy(fn ($t) => $t->category?->name ?? 'Sem categoria')->map->sum('amount')->sortDesc(),
        ]);
    }

    public function pay(Request $request, CreditCard $card, TransactionService $service)
    {
        if ($request->filled('amount')) {
            $request->merge(['amount' => parse_money($request->input('amount'))]);
        }
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('household_id', household()->id)],
            'amount' => ['nullable', 'numeric', 'gt:0'],
        ]);
        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $remaining = $card->invoiceRemaining($month);
        $service->payInvoice($card, $month, Account::findOrFail($data['account_id']), $data['amount'] ?? null);

        return back()->with('success', isset($data['amount']) && $data['amount'] < $remaining
            ? 'Pagamento parcial registrado. O saldo de '.brl($remaining - $data['amount']).' foi para a próxima fatura — ali o banco cobra juros do rotativo (frequentemente acima de 10% ao mês). Se possível, quite logo.'
            : 'Fatura paga! Os lançamentos foram baixados.');
    }

    public function anticipate(Request $request, Transaction $transaction, TransactionService $service)
    {
        if ($request->filled('discount')) {
            $request->merge(['discount' => parse_money($request->input('discount'))]);
        }
        $data = $request->validate(['count' => ['required', 'integer', 'min:1', 'max:120'], 'discount' => ['nullable', 'numeric', 'min:0']]);
        $n = $service->anticipateInstallments($transaction, $data['count'], (float) ($data['discount'] ?? 0));

        return back()->with('success', $n ? "{$n} parcela(s) antecipada(s) para a fatura atual." : 'Não há parcelas futuras para antecipar.');
    }

    private function validated(Request $request): array
    {
        $request->merge(['limit' => parse_money($request->input('limit', 0))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'brand' => ['nullable', 'string', 'max:30'],
            'limit' => ['required', 'numeric', 'min:0'],
            'closing_day' => ['required', 'integer', 'min:1', 'max:31'],
            'due_day' => ['required', 'integer', 'min:1', 'max:31'],
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where('household_id', household()->id)],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [], ['name' => 'nome', 'limit' => 'limite', 'closing_day' => 'dia de fechamento', 'due_day' => 'dia de vencimento']);
        $data['is_private'] = $request->boolean('is_private');
        $data['is_shared'] = ! $data['is_private'] && $request->boolean('is_shared');
        $data['archived'] = $request->boolean('archived');

        return $data;
    }
}
