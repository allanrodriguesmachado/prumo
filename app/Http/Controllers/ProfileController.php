<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'telegramEnabled' => (bool) config('prumo.telegram.token'),
            'telegramBot' => config('prumo.telegram.username'),
            'providers' => app(\App\Services\Ai\AiAdvisor::class)->availableProviders(),
            'brapi' => (bool) config('prumo.brapi_token'),
            'audit' => AuditLog::where('user_id', $request->user()->id)->latest('created_at')->take(15)->get(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($user->id)],
            'work_hours_per_month' => ['required', 'numeric', 'min:20', 'max:400'],
        ]);
        $data['notify_email'] = $request->boolean('notify_email');
        $data['notify_telegram'] = $request->boolean('notify_telegram');
        $user->update($data);

        return back()->with('success', 'Perfil atualizado.');
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['current_password' => 'senha atual', 'password' => 'nova senha']);
        $request->user()->update(['password' => Hash::make($request->input('password'))]);
        AuditLog::record('password_changed');

        return back()->with('success', 'Senha alterada.');
    }

    public function telegram(Request $request)
    {
        $request->user()->forceFill(['telegram_link_code' => strtoupper(Str::random(8))])->save();

        return back()->with('success', 'Código gerado! Envie para o bot no Telegram.');
    }

    public function destroy(Request $request)
    {
        $request->validateWithBag('deleteAccount', ['password' => ['required', 'current_password']]);
        $user = $request->user();
        AuditLog::record('account_deleted', null, ['user_id' => $user->id]);
        Auth::logout();
        // carteiras onde é o único membro são apagadas junto
        foreach ($user->households as $h) {
            if ($h->members()->count() <= 1) {
                $h->delete();
            } else {
                // mantém o histórico do casal: transfere os registros para o(a) parceiro(a)
                $partnerId = $h->members()->where('users.id', '!=', $user->id)->value('users.id');
                foreach (['transactions', 'accounts', 'credit_cards', 'recurrences', 'debts', 'goals', 'assets', 'imports'] as $table) {
                    \Illuminate\Support\Facades\DB::table($table)->where('household_id', $h->id)->where('user_id', $user->id)->update(['user_id' => $partnerId]);
                }
                $h->update(['owner_id' => $partnerId, 'type' => 'individual']);
            }
        }
        $user->delete();
        $request->session()->invalidate();

        return redirect()->route('login')->with('status', 'Conta excluída. Sentiremos sua falta!');
    }

    /** LGPD (art. 18): portabilidade — todos os dados que o usuário pode ver, em JSON. */
    public function export(Request $request)
    {
        $user = $request->user();
        $h = household();
        AuditLog::record('data_exported');
        $data = [
            'gerado_em' => now()->toIso8601String(),
            'usuario' => $user->only(['name', 'email', 'created_at', 'work_hours_per_month', 'notify_email', 'notify_telegram']),
            'carteira' => $h->only(['name', 'type', 'split_mode', 'created_at']),
            'contas' => \App\Models\Account::all()->toArray(),
            'cartoes' => \App\Models\CreditCard::all()->toArray(),
            'categorias' => \App\Models\Category::all()->toArray(),
            'recorrencias' => \App\Models\Recurrence::all()->toArray(),
            'lancamentos' => \App\Models\Transaction::orderBy('date')->get()->toArray(),
            'dividas' => \App\Models\Debt::all()->toArray(),
            'metas' => \App\Models\Goal::all()->toArray(),
            'investimentos' => \App\Models\Asset::with('operations')->get()->toArray(),
            'conversas_ia' => \App\Models\AiConversation::where('user_id', $user->id)->with('messages')->get()->toArray(),
            'auditoria' => AuditLog::where('user_id', $user->id)->latest('created_at')->take(500)->get()->toArray(),
        ];

        return response()->streamDownload(fn () => print(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)), 'prumo-meus-dados-'.now()->format('Y-m-d').'.json', ['Content-Type' => 'application/json']);
    }

    public function readNotifications(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
