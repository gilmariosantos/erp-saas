<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inicializa o contexto de tenant.
 * Estende o middleware padrão do stancl/tenancy adicionando:
 *  - Verificação de assinatura ativa
 *  - Cache do tenant para evitar queries desnecessárias
 *  - Header X-Tenant-ID na resposta (útil para debug)
 */
class TenantMiddleware extends InitializeTenancyByDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        // Deixa o tenancy inicializar pelo domínio
        $response = parent::handle($request, $next);

        // Após inicializado, verifica assinatura
        if (tenancy()->initialized) {
            $tenant = tenant();

            // Adiciona header de identificação (apenas em dev)
            if (config('app.debug')) {
                $response->headers->set('X-Tenant-ID', $tenant->id);
            }

            // Verifica se assinatura está ativa
            $subscription = Cache::remember(
                "tenant_{$tenant->id}_subscription",
                now()->addMinutes(5),
                fn () => \App\Models\TenantSubscription::where('tenant_id', $tenant->id)
                    ->whereIn('status', ['trial', 'active'])
                    ->first()
            );

            if (! $subscription) {
                return response()->json([
                    'message' => 'Assinatura inativa ou expirada. Acesse o painel para regularizar.',
                    'code'    => 'SUBSCRIPTION_INACTIVE',
                ], 402);
            }
        }

        return $response;
    }
}
