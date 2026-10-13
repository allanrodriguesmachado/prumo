<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Household;
use App\Services\CoupleSettlement;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CoupleController extends Controller
{
    public function index(Request $request)
    {
        $household = household()->load('members');
        $month = $request->filled('mes') ? Carbon::createFromFormat('Y-m', $request->query('mes'))->startOfMonth() : now()->startOfMonth();

        return view('couple.index', [
            'household' => $household,
            'month' => $month,
            'settlement' => (new CoupleSettlement($household))->forMonth($month),
            'inviteUrl' => route('invite', $household->invite_code),
            'households' => $request->user()->households()->get(),
            'accounts' => Account::where('archived', false)->get(),
            'settled' => $household->setting('settled.'.$month->format('Y-m')),
            'myRole' => $household->roleOf($request->user()),
            'partnerRole' => ($p = $household->partnerOf($request->user())) ? $household->roleOf($p) : null,
            'pending' => \App\Models\Transaction::with('user')->where('approval_status', 'pending')->orderBy('date')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $household = household();
        abort_unless($household->roleOf($request->user()) === 'owner', 403, 'Só o titular altera as configurações da carteira.');
        if ($request->filled('approval_threshold')) {
            $request->merge(['approval_threshold' => parse_money($request->input('approval_threshold'))]);
        }
        $data = $request->validate([
            'approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'partner_role' => ['nullable', Rule::in(['member', 'viewer'])],
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(['individual', 'couple'])],
            'split_mode' => ['required', Rule::in(['equal', 'proportional', 'custom'])],
            'split_owner_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'regenerate_code' => ['nullable', 'boolean'],
        ]);
        if ($request->boolean('regenerate_code')) {
            $data['invite_code'] = strtoupper(\Illuminate\Support\Str::random(8));
        }
        unset($data['regenerate_code']);
        $household->putSetting('approval_threshold', (float) ($data['approval_threshold'] ?? 0));
        if (! empty($data['partner_role']) && ($partner = $household->partnerOf($request->user()))) {
            $household->members()->updateExistingPivot($partner->id, ['role' => $data['partner_role']]);
            \App\Models\AuditLog::record('role_changed', $household, ['user_id' => $partner->id, 'role' => $data['partner_role']]);
        }
        unset($data['approval_threshold'], $data['partner_role']);
        $household->update($data);

        return back()->with('success', 'Configurações salvas.');
    }

    /** Registra a transferência do acerto entre o casal. */
    public function settle(Request $request, TransactionService $service)
    {
        $household = household();
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $s = (new CoupleSettlement($household))->forMonth($month);
        abort_unless($s && $s['transfer'], 422, 'Não há acerto pendente.');

        $household->putSetting('settled.'.$month->format('Y-m'), [
            'amount' => $s['transfer']['amount'], 'from' => $s['transfer']['from']->name, 'to' => $s['transfer']['to']->name, 'at' => now()->toDateTimeString(),
        ]);

        return back()->with('success', 'Acerto de '.brl($s['transfer']['amount']).' registrado. Contas em dia, relacionamento em paz! 💚');
    }

    public function approve(Request $request, \App\Models\Transaction $transaction, TransactionService $service)
    {
        $service->decideApproval($transaction, $request->user(), $request->input('decision') === 'approve');

        return back()->with('success', $request->input('decision') === 'approve'
            ? 'Despesa aprovada — já entra no acerto do casal.'
            : 'Despesa recusada — ela fica como gasto individual de quem lançou.');
    }

    public function switch(Request $request, Household $household)
    {
        abort_unless($household->members()->where('users.id', $request->user()->id)->exists(), 404);
        $request->user()->forceFill(['current_household_id' => $household->id])->save();

        return redirect()->route('dashboard')->with('success', "Carteira \"{$household->name}\" ativa.");
    }
}
