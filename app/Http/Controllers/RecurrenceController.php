<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Recurrence;
use App\Services\FinanceAnalytics;
use App\Services\RecurrenceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecurrenceController extends Controller
{
    public function __construct(private RecurrenceService $service) {}

    public function index()
    {
        $items = Recurrence::with(['category', 'account', 'creditCard', 'user'])->orderByDesc('active')->orderBy('day_of_month')->get();
        $a = FinanceAnalytics::current();
        $incomes = $items->where('type', 'income');
        $expenses = $items->where('type', 'expense');
        $monthlyIncome = $incomes->where('active', true)->sum(fn ($r) => $r->monthlyAmount());
        $monthlyFixed = $expenses->where('active', true)->sum(fn ($r) => $r->monthlyAmount());

        return view('recurrences.index', [
            'salaries' => $incomes->where('kind', 'salary'),
            'extras' => $incomes->where('kind', '!=', 'salary'),
            'fixed' => $expenses,
            'monthlyIncome' => $monthlyIncome,
            'monthlyFixed' => $monthlyFixed,
            'commitment' => $monthlyIncome > 0 ? $monthlyFixed / $monthlyIncome * 100 : 0,
            'avgDaily' => $a->averageMonthlySpending(),
        ]);
    }

    public function create(Request $request)
    {
        $kind = $request->query('tipo', 'fixed');

        return view('recurrences.form', $this->formData() + ['recurrence' => new Recurrence([
            'kind' => $kind, 'type' => $kind === 'fixed' ? 'expense' : 'income', 'frequency' => 'monthly',
            'day_of_month' => $kind === 'salary' ? 5 : 10, 'start_date' => now()->startOfMonth(), 'active' => true,
            'is_shared' => household()->isCouple() && $kind === 'fixed',
        ])]);
    }

    public function store(Request $request)
    {
        $r = Recurrence::create($this->validated($request));
        $n = $this->service->generate($r);

        return redirect()->route('recurrences.index')->with('success', "Salvo! {$n} lançamento(s) previsto(s) gerado(s).");
    }

    public function edit(Recurrence $recurrence)
    {
        return view('recurrences.form', $this->formData() + ['recurrence' => $recurrence]);
    }

    public function update(Request $request, Recurrence $recurrence)
    {
        $recurrence->update($this->validated($request));
        $this->service->syncFuture($recurrence);

        return redirect()->route('recurrences.index')->with('success', 'Atualizado. Os lançamentos futuros pendentes foram recalculados.');
    }

    public function destroy(Request $request, Recurrence $recurrence)
    {
        $recurrence->transactions()->where('paid', false)->whereDate('date', '>=', now()->toDateString())->delete();
        $recurrence->delete();

        return redirect()->route('recurrences.index')->with('success', 'Removido. Lançamentos já pagos foram mantidos no histórico.');
    }

    private function formData(): array
    {
        return [
            'categories' => Category::where('archived', false)->orderBy('name')->get(),
            'accounts' => Account::where('archived', false)->get(),
            'cards' => CreditCard::where('archived', false)->get(),
            'members' => household()->members,
        ];
    }

    private function validated(Request $request): array
    {
        $request->merge(['amount' => parse_money($request->input('amount'))]);
        $method = (string) $request->input('method', '');
        $request->merge([
            'credit_card_id' => str_starts_with($method, 'card:') ? (int) substr($method, 5) : null,
            'account_id' => str_starts_with($method, 'account:') ? (int) substr($method, 8) : null,
        ]);
        $hid = household()->id;
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(Recurrence::KINDS))],
            'description' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'frequency' => ['required', Rule::in(array_keys(Recurrence::FREQUENCIES))],
            'day_of_month' => ['required', 'integer', 'min:1', 'max:31'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('household_id', $hid)],
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where('household_id', $hid)],
            'credit_card_id' => ['nullable', Rule::exists('credit_cards', 'id')->where('household_id', $hid)],
            'user_id' => ['nullable', Rule::exists('household_user', 'user_id')->where('household_id', $hid)],
        ], [], ['description' => 'descrição', 'amount' => 'valor', 'day_of_month' => 'dia']);
        $data['type'] = $data['kind'] === 'fixed' ? 'expense' : 'income';
        $data['is_shared'] = $request->boolean('is_shared');
        $data['auto_paid'] = $request->boolean('auto_paid');
        $data['active'] = $request->boolean('active', true);
        $data['user_id'] ??= $request->user()->id;

        return $data;
    }
}
