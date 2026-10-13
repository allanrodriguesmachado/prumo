<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHousehold
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && (! $user->current_household_id || ! $user->households()->whereKey($user->current_household_id)->exists())) {
            $first = $user->households()->first();
            if ($first) {
                $user->forceFill(['current_household_id' => $first->id])->save();
            } else {
                return redirect()->route('onboarding');
            }
        }

        // perfil "somente leitura": pode ver tudo e conversar com a IA, mas não altera dados financeiros
        if ($user && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
            && ! $request->routeIs(self::READONLY_ALLOWED)
            && ! $user->currentHousehold?->canWrite($user)) {
            if ($request->expectsJson()) {
                abort(403, 'Seu acesso a esta carteira é somente leitura.');
            }

            return back()->with('error', 'Seu acesso a esta carteira é somente leitura. Peça ao titular para mudar seu papel em Casal → Papéis.');
        }

        return $next($request);
    }

    /** Ações permitidas para quem tem acesso somente leitura (não alteram dados financeiros). */
    private const READONLY_ALLOWED = [
        'ai.send', 'ai.destroy', 'household.switch', 'notifications.read',
        'profile.*', 'two-factor.*', 'logout',
    ];
}
