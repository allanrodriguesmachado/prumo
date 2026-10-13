<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::validate($credentials)) {
            AuditLog::record('login_failed', null, ['email' => mb_substr($credentials['email'], 0, 3).'***']);

            return back()->withErrors(['email' => 'E-mail ou senha incorretos.'])->onlyInput('email');
        }
        $user = User::where('email', $credentials['email'])->firstOrFail();

        // com 2FA ativo, a sessão só é criada depois do código
        if ($user->hasTwoFactor()) {
            $request->session()->regenerate();
            session(['2fa.login' => ['id' => $user->id, 'remember' => $request->boolean('remember'), 'at' => now()->timestamp]]);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        AuditLog::record('login', $user);

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(Request $request)
    {
        return view('auth.register', ['invite' => $request->query('convite')]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'invite' => ['nullable', 'string', 'max:16'],
            'terms' => ['accepted'],
        ], [], ['name' => 'nome', 'password' => 'senha', 'terms' => 'termos']);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
        ]);
        Auth::login($user);
        $request->session()->regenerate();

        if (! empty($data['invite'])) {
            return redirect()->route('invite', $data['invite']);
        }

        return redirect()->route('onboarding');
    }

    public function logout(Request $request)
    {
        AuditLog::record('logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
