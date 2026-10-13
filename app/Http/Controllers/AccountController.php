<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\Market\BrasilApi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Account::with('user')->orderBy('archived')->orderBy('name')->get();

        return view('accounts.index', [
            'accounts' => $accounts,
            'balances' => $accounts->mapWithKeys(fn ($a) => [$a->id => $a->balance()]),
            'projected' => $accounts->mapWithKeys(fn ($a) => [$a->id => $a->projectedBalance(now()->endOfMonth()->toDateString())]),
        ]);
    }

    /** Extrato da conta com saldo corrido, pendências e conciliação. */
    public function show(\Illuminate\Http\Request $request, Account $account)
    {
        $month = $request->filled('mes') ? \Carbon\Carbon::createFromFormat('Y-m', $request->query('mes'))->startOfMonth() : now()->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $opening = $account->balance($start->copy()->subDay()->toDateString(), true);
        $items = \App\Models\Transaction::with('category')
            ->where(fn ($q) => $q->where('account_id', $account->id)->orWhere('transfer_account_id', $account->id))
            ->whereNull('credit_card_id')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')->orderBy('id')->get();

        $running = $opening;
        $rows = $items->map(function ($t) use ($account, &$running) {
            $in = $t->type === 'income' || ($t->type === 'transfer' && $t->transfer_account_id === $account->id);
            $value = $in ? (float) $t->amount : -(float) $t->amount;
            $running += $value;

            return ['t' => $t, 'value' => $value, 'balance' => round($running, 2)];
        });

        return view('accounts.show', [
            'account' => $account, 'month' => $month, 'rows' => $rows, 'opening' => $opening, 'closing' => round($running, 2),
            'current' => $account->balance(), 'pending' => $rows->filter(fn ($r) => ! $r['t']->paid)->sum('value'),
            'unreconciled' => $rows->filter(fn ($r) => $r['t']->paid && ! $r['t']->reconciled_at)->count(),
        ]);
    }

    /** Marca/desmarca um lançamento como conferido com o extrato do banco. */
    public function reconcile(Account $account, \App\Models\Transaction $transaction)
    {
        abort_unless($transaction->account_id === $account->id || $transaction->transfer_account_id === $account->id, 404);
        $transaction->update(['reconciled_at' => $transaction->reconciled_at ? null : now(), 'paid' => true]);

        return back()->with('success', $transaction->reconciled_at ? 'Lançamento conferido com o banco. ✔️' : 'Conferência removida.');
    }

    public function create(BrasilApi $brasil)
    {
        return view('accounts.form', ['account' => new Account(['type' => 'checking', 'color' => '#0f766e', 'is_shared' => household()->isCouple()]), 'banks' => $brasil->banks()]);
    }

    public function store(Request $request)
    {
        Account::create($this->validated($request));

        return redirect()->route('accounts.index')->with('success', 'Conta criada.');
    }

    public function edit(Account $account, BrasilApi $brasil)
    {
        return view('accounts.form', ['account' => $account, 'banks' => $brasil->banks()]);
    }

    public function update(Request $request, Account $account)
    {
        $account->update($this->validated($request));

        return redirect()->route('accounts.index')->with('success', 'Conta atualizada.');
    }

    public function destroy(Account $account)
    {
        if ($account->transactions()->exists()) {
            $account->update(['archived' => true]);

            return back()->with('success', 'A conta tem lançamentos e foi arquivada.');
        }
        $account->delete();

        return back()->with('success', 'Conta excluída.');
    }

    private function validated(Request $request): array
    {
        $request->merge(['initial_balance' => parse_money($request->input('initial_balance', 0))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(array_keys(Account::TYPES))],
            'bank_code' => ['nullable', 'string', 'max:10'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'initial_balance' => ['required', 'numeric'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [], ['name' => 'nome', 'initial_balance' => 'saldo inicial']);
        $data['is_private'] = $request->boolean('is_private');
        $data['is_shared'] = ! $data['is_private'] && $request->boolean('is_shared');
        $data['archived'] = $request->boolean('archived');

        return $data;
    }
}
