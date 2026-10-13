<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorService $tfa) {}

    // ---------- ativação (usuário logado)

    public function setup(Request $request)
    {
        $user = $request->user();
        abort_if($user->hasTwoFactor(), 409);
        $secret = session('2fa.pending_secret') ?? $this->tfa->generateSecret();
        session(['2fa.pending_secret' => $secret]);

        return view('auth.two-factor-setup', ['qr' => $this->tfa->qrSvg($user, $secret), 'secret' => $secret]);
    }

    public function enable(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();
        $secret = session('2fa.pending_secret');
        if (! $secret || ! $this->tfa->verify($user, $request->input('code'), $secret)) {
            return back()->withErrors(['code' => 'Código inválido. Confira o horário do celular e tente de novo.']);
        }
        $codes = $this->tfa->newRecoveryCodes();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => $codes, 'two_factor_confirmed_at' => now()])->save();
        session()->forget('2fa.pending_secret');
        AuditLog::record('2fa_enabled', $user);

        return redirect()->route('profile.edit')->with('recovery_codes', $codes)->with('success', 'Verificação em duas etapas ativada! Guarde os códigos de recuperação.');
    }

    public function disable(Request $request)
    {
        $request->validateWithBag('twoFactor', ['password' => ['required', 'current_password']]);
        $request->user()->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        AuditLog::record('2fa_disabled', $request->user());

        return back()->with('success', 'Verificação em duas etapas desativada.');
    }

    public function regenerate(Request $request)
    {
        $request->validateWithBag('twoFactor', ['password' => ['required', 'current_password']]);
        $codes = $this->tfa->newRecoveryCodes();
        $request->user()->forceFill(['two_factor_recovery_codes' => $codes])->save();
        AuditLog::record('2fa_recovery_regenerated', $request->user());

        return back()->with('recovery_codes', $codes)->with('success', 'Novos códigos de recuperação gerados.');
    }

    // ---------- desafio no login (usuário ainda não autenticado)

    public function challenge(Request $request)
    {
        abort_unless(session()->has('2fa.login'), 403);

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $pending = session('2fa.login');
        abort_unless($pending && now()->timestamp - $pending['at'] < 600, 403, 'Sessão de login expirada. Entre novamente.');
        $request->validate(['code' => ['nullable', 'string'], 'recovery_code' => ['nullable', 'string']]);
        $user = User::findOrFail($pending['id']);

        $ok = $request->filled('recovery_code')
            ? $this->tfa->useRecoveryCode($user, $request->input('recovery_code'))
            : $this->tfa->verify($user, (string) $request->input('code'));

        if (! $ok) {
            AuditLog::record('2fa_failed', $user, [], $user);

            return back()->withErrors(['code' => 'Código inválido.']);
        }
        session()->forget('2fa.login');
        Auth::login($user, $pending['remember']);
        $request->session()->regenerate();
        AuditLog::record($request->filled('recovery_code') ? 'login_recovery_code' : 'login', $user);

        return redirect()->intended(route('dashboard'));
    }
}
