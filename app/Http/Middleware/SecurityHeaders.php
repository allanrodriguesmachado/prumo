<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;
        $h->set('X-Frame-Options', 'DENY');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        if (! app()->environment('local')) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        // Alpine.js precisa de 'unsafe-eval'; scripts e estilos são servidos pelo próprio domínio
        $vite = app()->environment('local') ? ' http://localhost:5173 ws://localhost:5173' : '';
        $h->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'{$vite}; style-src 'self' 'unsafe-inline'{$vite}; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'{$vite}; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
        // páginas autenticadas nunca em cache compartilhado
        if ($request->user()) {
            $h->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
