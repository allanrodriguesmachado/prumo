<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Recurrence;
use App\Services\HouseholdService;
use App\Services\Market\BrasilApi;
use App\Services\RecurrenceService;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function show(Request $request)
    {
        if ($request->user()->households()->exists() && ! $request->boolean('nova')) {
            return redirect()->route('dashboard');
        }

        return view('onboarding.start', ['invite' => session('invite_code')]);
    }

    public function store(Request $request, HouseholdService $service)
    {
        $data = $request->validate([
            'type' => ['required', 'in:individual,couple'],
            'name' => ['nullable', 'string', 'max:80'],
        ]);
        $service->createFor($request->user(), $data['type'], $data['name'] ?? null);

        return redirect()->route('setup');
    }

    public function join(Request $request, HouseholdService $service)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:16']]);
        $household = $service->join($request->user(), $data['code']);
        if (! $household) {
            return back()->withErrors(['code' => 'Convite inválido ou a carteira do casal já tem duas pessoas.']);
        }

        return redirect()->route('setup')->with('success', "Você entrou em \"{$household->name}\". 💑");
    }

    public function invite(Request $request, string $code, HouseholdService $service)
    {
        if (! $request->user()) {
            session(['invite_code' => $code]);

            return redirect()->route('register', ['convite' => $code]);
        }
        $household = $service->join($request->user(), $code);
        if (! $household) {
            return redirect()->route('onboarding')->withErrors(['code' => 'Convite inválido ou expirado.']);
        }

        return redirect()->route('setup')->with('success', "Você entrou em \"{$household->name}\". 💑");
    }

    /** Assistente: salário, renda extra, gastos fixos e conta principal. */
    public function setup(BrasilApi $brasil)
    {
        return view('onboarding.setup', [
            'banks' => $brasil->banks(),
            'household' => household(),
        ]);
    }

    public function saveSetup(Request $request, RecurrenceService $recurrences)
    {
        $data = $request->validate([
            'bank_code' => ['nullable', 'string', 'max:10'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'balance' => ['nullable'],
            'salary' => ['nullable'],
            'salary_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'extra' => ['nullable'],
            'fixed' => ['array'],
            'fixed.*.description' => ['nullable', 'string', 'max:120'],
            'fixed.*.amount' => ['nullable'],
            'fixed.*.day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'work_hours' => ['nullable', 'numeric', 'min:20', 'max:400'],
        ]);
        $user = $request->user();

        $account = null;
        if (! empty($data['bank_name']) || parse_money($data['balance'] ?? 0) != 0) {
            $account = Account::create([
                'name' => $data['bank_name'] ?: 'Conta principal', 'type' => 'checking',
                'bank_code' => $data['bank_code'] ?? null, 'bank_name' => $data['bank_name'] ?? null,
                'initial_balance' => parse_money($data['balance'] ?? 0), 'color' => '#0f766e',
            ]);
        }
        $account ??= Account::where('user_id', $user->id)->first();

        $cat = fn ($name, $type) => Category::where('name', $name)->where('type', $type)->value('id');
        $made = [];
        if (($salary = parse_money($data['salary'] ?? 0)) > 0) {
            $made[] = Recurrence::create([
                'type' => 'income', 'kind' => 'salary', 'description' => 'Salário '.$user->firstName(), 'amount' => $salary,
                'category_id' => $cat('Salário', 'income'), 'account_id' => $account?->id, 'day_of_month' => $data['salary_day'] ?? 5,
                'start_date' => now()->startOfMonth(), 'auto_paid' => false,
            ]);
        }
        if (($extra = parse_money($data['extra'] ?? 0)) > 0) {
            $made[] = Recurrence::create([
                'type' => 'income', 'kind' => 'extra_income', 'description' => 'Renda extra '.$user->firstName(), 'amount' => $extra,
                'category_id' => $cat('Renda extra', 'income'), 'account_id' => $account?->id, 'day_of_month' => 20,
                'start_date' => now()->startOfMonth(),
            ]);
        }
        foreach ($data['fixed'] ?? [] as $f) {
            if (empty($f['description']) || parse_money($f['amount'] ?? 0) <= 0) {
                continue;
            }
            $guess = app(\App\Services\Import\Categorizer::class)->guess($f['description'], 'expense');
            $made[] = Recurrence::create([
                'type' => 'expense', 'kind' => 'fixed', 'description' => $f['description'], 'amount' => parse_money($f['amount']),
                'category_id' => $guess?->id ?? $cat('Moradia', 'expense'), 'account_id' => $account?->id,
                'day_of_month' => $f['day'] ?? 10, 'start_date' => now()->startOfMonth(), 'is_shared' => household()->isCouple(),
            ]);
        }
        foreach ($made as $r) {
            $recurrences->generate($r);
        }

        $user->forceFill(['onboarded_at' => now(), 'work_hours_per_month' => $data['work_hours'] ?? $user->work_hours_per_month])->save();

        return redirect()->route('dashboard')->with('success', 'Tudo pronto! Este é o seu painel. 🎉');
    }
}
