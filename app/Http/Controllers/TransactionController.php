<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Debt;
use App\Models\Goal;
use App\Models\Transaction;
use App\Services\FinanceAnalytics;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function __construct(private TransactionService $service) {}

    public function index(Request $request)
    {
        $month = $request->filled('mes') ? Carbon::createFromFormat('Y-m', $request->query('mes'))->startOfMonth() : now()->startOfMonth();
        $query = $this->filtered($request, $month);

        $transactions = (clone $query)->with(['category', 'account', 'creditCard', 'user'])
            ->orderByDesc('date')->orderByDesc('id')->paginate(50)->withQueryString();
        $a = FinanceAnalytics::current();

        return view('transactions.index', [
            'transactions' => $transactions,
            'month' => $month,
            'summary' => $a->monthSummary($month),
            'totals' => [
                'income' => (float) (clone $query)->where('type', 'income')->whereNull('credit_card_id')->sum('amount'),
                'expense' => (float) (clone $query)->where('type', 'expense')->where('kind', '!=', 'invoice_payment')->sum('amount'),
            ],
            'categories' => Category::where('archived', false)->orderBy('name')->get(),
            'accounts' => Account::where('archived', false)->get(),
            'cards' => CreditCard::where('archived', false)->get(),
            'members' => household()->members,
            'hourly' => $a->hourlyRate($request->user()),
            'filters' => $request->only(['tipo', 'categoria', 'conta', 'cartao', 'busca', 'status', 'pessoa', 'natureza']),
        ]);
    }

    public function create(Request $request)
    {
        return view('transactions.form', $this->formData($request) + ['transaction' => new Transaction([
            'type' => $request->query('tipo', 'expense'),
            'kind' => $request->query('natureza', $request->query('tipo') === 'income' ? 'extra_income' : 'daily'),
            'date' => now(),
            'paid' => true,
            'is_shared' => household()->isCouple() && $request->query('tipo', 'expense') === 'expense',
            'debt_id' => $request->query('divida'),
            'goal_id' => $request->query('meta'),
        ])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        // proteção contra duplo envio (clique duplo, reenvio do navegador): mesmo lançamento em 10s
        $fingerprint = 'tx-submit:'.$request->user()->id.':'.md5(json_encode([$data['type'], $data['description'], $data['amount'], $data['date'], $data['installments'] ?? 1]));
        if (! cache()->add($fingerprint, true, 10)) {
            return redirect()->route('transactions.index', ['mes' => Carbon::parse($data['date'])->format('Y-m')])
                ->with('success', 'Este lançamento acabou de ser salvo — ignoramos o envio repetido.');
        }
        $created = $this->service->create($data);
        $msg = count($created) > 1 ? count($created).' parcelas lançadas.' : 'Lançamento salvo.';

        if ($request->boolean('another')) {
            return redirect()->route('transactions.create', ['tipo' => $data['type'], 'natureza' => $data['kind']])->with('success', $msg);
        }

        return redirect()->route('transactions.index', ['mes' => Carbon::parse($data['date'])->format('Y-m')])->with('success', $msg);
    }

    public function edit(Request $request, Transaction $transaction)
    {
        return view('transactions.form', $this->formData($request) + ['transaction' => $transaction]);
    }

    public function update(Request $request, Transaction $transaction)
    {
        $data = $this->validated($request, $transaction);
        $this->service->update($transaction, $data);

        return redirect()->route('transactions.index', ['mes' => $transaction->date->format('Y-m')])->with('success', 'Lançamento atualizado.');
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        $this->service->delete($transaction, $request->boolean('all'));

        return back()->with('success', 'Lançamento excluído.');
    }

    public function togglePaid(Transaction $transaction)
    {
        abort_if($transaction->credit_card_id, 422, 'Lançamentos de cartão são baixados ao pagar a fatura.');
        $transaction->update(['paid' => ! $transaction->paid]);

        return back()->with('success', $transaction->paid ? 'Marcado como pago/recebido.' : 'Marcado como pendente.');
    }

    public function export(Request $request)
    {
        $month = $request->filled('mes') ? Carbon::createFromFormat('Y-m', $request->query('mes'))->startOfMonth() : now()->startOfMonth();
        $rows = $this->filtered($request, $month)->with(['category', 'account', 'creditCard', 'user'])->orderBy('date')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Data', 'Descrição', 'Tipo', 'Natureza', 'Categoria', 'Conta/Cartão', 'Valor', 'Pago', 'Compartilhado', 'Pessoa'], ';');
            foreach ($rows as $t) {
                fputcsv($out, [
                    $t->date->format('d/m/Y'), $t->label(), $t->type === 'income' ? 'Receita' : ($t->type === 'transfer' ? 'Transferência' : 'Despesa'),
                    Transaction::KINDS[$t->kind] ?? $t->kind, $t->category?->name, $t->creditCard?->name ?? $t->account?->name,
                    number_format(($t->type === 'income' ? 1 : -1) * (float) $t->amount, 2, ',', ''), $t->paid ? 'Sim' : 'Não',
                    $t->is_shared ? 'Sim' : 'Não', $t->user?->name,
                ], ';');
            }
            fclose($out);
        }, 'prumo-lancamentos-'.$month->format('Y-m').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtered(Request $request, Carbon $month)
    {
        return Transaction::query()
            ->when(! $request->filled('busca') || $request->filled('mes'), fn ($q) => $q->inMonth($month))
            ->when($request->query('tipo'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->query('natureza'), fn ($q, $v) => $q->where('kind', $v))
            ->when($request->query('categoria'), fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->query('conta'), fn ($q, $v) => $q->where('account_id', $v))
            ->when($request->query('cartao'), fn ($q, $v) => $q->where('credit_card_id', $v))
            ->when($request->query('pessoa'), fn ($q, $v) => $v === 'shared' ? $q->where('is_shared', true) : $q->where('user_id', $v))
            ->when($request->query('status') === 'pendente', fn ($q) => $q->where('paid', false))
            ->when($request->query('status') === 'pago', fn ($q) => $q->where('paid', true))
            ->when($request->query('busca'), fn ($q, $v) => $q->where('description', 'ilike', '%'.$v.'%'));
    }

    private function formData(Request $request): array
    {
        return [
            'categories' => Category::where('archived', false)->orderBy('name')->get(),
            'accounts' => Account::where('archived', false)->get(),
            'cards' => CreditCard::where('archived', false)->get(),
            'debts' => Debt::where('status', '!=', 'paid')->get(),
            'goals' => Goal::whereNull('achieved_at')->get(),
            'hourly' => FinanceAnalytics::current()->hourlyRate($request->user()),
        ];
    }

    private function validated(Request $request, ?Transaction $existing = null): array
    {
        $request->merge(['amount' => parse_money($request->input('amount'))]);
        $method = (string) $request->input('method', '');
        if (str_starts_with($method, 'card:')) {
            $request->merge(['credit_card_id' => (int) substr($method, 5), 'account_id' => null]);
        } elseif (str_starts_with($method, 'account:')) {
            $request->merge(['account_id' => (int) substr($method, 8), 'credit_card_id' => null]);
        }

        $hid = household()->id;
        $data = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense', 'transfer'])],
            'kind' => ['required', Rule::in(array_keys(Transaction::KINDS))],
            'description' => ['required', 'string', 'max:190'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'date' => ['required', 'date'],
            'paid' => ['boolean'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('household_id', $hid)],
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where('household_id', $hid)],
            'transfer_account_id' => ['nullable', 'required_if:type,transfer', 'different:account_id', Rule::exists('accounts', 'id')->where('household_id', $hid)],
            'credit_card_id' => ['nullable', Rule::exists('credit_cards', 'id')->where('household_id', $hid)],
            'installments' => ['nullable', 'integer', 'min:1', 'max:120'],
            'installment_value_mode' => ['nullable', 'in:total,each'],
            'debt_id' => ['nullable', Rule::exists('debts', 'id')->where('household_id', $hid)],
            'goal_id' => ['nullable', Rule::exists('goals', 'id')->where('household_id', $hid)],
            'is_shared' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'description' => 'descrição', 'amount' => 'valor', 'date' => 'data', 'transfer_account_id' => 'conta de destino',
        ]);
        $data['paid'] = $request->boolean('paid');
        $data['is_shared'] = $request->boolean('is_shared');
        $data['is_private'] = $request->boolean('is_private');
        if ($data['type'] === 'transfer') {
            $data['kind'] = 'transfer';
            $data['credit_card_id'] = null;
            $data['paid'] = true;
        }

        return $data;
    }
}
