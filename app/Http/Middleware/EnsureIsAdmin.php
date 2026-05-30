<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante que o usuário autenticado é um AdminUser (super-admin do SaaS).
 */
class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof \App\Models\AdminUser) {
            return response()->json(['message' => 'Acesso restrito a administradores do sistema.'], 403);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Conta administrativa desativada.'], 403);
        }

        return $next($request);
    }
}
