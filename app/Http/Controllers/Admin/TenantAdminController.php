<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin (Super-Admin)
 *
 * Painel de gestão de TODOS os tenants do SaaS.
 * Acesso restrito a usuários admin_users (guard: admin).
 */
class TenantAdminController extends Controller
{
    public function __construct(
        private readonly TenantProvisioningService $provisioning,
    ) {}

    /**
     * Lista todos os tenants com filtros e estatísticas.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::query()
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where('razao_social', 'like', "%{$request->search}%")
                ->orWhere('cnpj', 'like', "%{$request->search}%")
                ->orWhere('id', 'like', "%{$request->search}%"))
            ->withCount('domains')
            ->latest();

        $tenants = $query->paginate((int) $request->get('per_page', 25));

        return response()->json([
            'data'  => $tenants,
            'resumo' => [
                'total'        => Tenant::count(),
                'ativos'       => Tenant::where('status', 'ativo')->count(),
                'suspensos'    => Tenant::where('status', 'suspenso')->count(),
                'provisionando'=> Tenant::where('status', 'provisionando')->count(),
            ],
        ]);
    }

    /**
     * Detalhes de um tenant específico (inclui assinatura e uso).
     */
    public function show(string $tenantId): JsonResponse
    {
        $tenant = Tenant::with('domains')->findOrFail($tenantId);

        $subscription = \Illuminate\Support\Facades\DB::table('tenant_subscriptions')
            ->where('tenant_id', $tenantId)
            ->latest()
            ->first();

        return response()->json([
            'tenant'       => $tenant,
            'assinatura'   => $subscription,
        ]);
    }

    /**
     * Suspende um tenant (ex: inadimplência).
     */
    public function suspender(Request $request, string $tenantId): JsonResponse
    {
        $request->validate(['motivo' => ['required', 'string', 'min:5']]);

        $tenant = Tenant::findOrFail($tenantId);
        $this->provisioning->suspender($tenant, $request->string('motivo'));

        return response()->json(['message' => "Tenant {$tenantId} suspenso."]);
    }

    /**
     * Reativa um tenant suspenso.
     */
    public function reativar(string $tenantId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        $this->provisioning->reativar($tenant);

        return response()->json(['message' => "Tenant {$tenantId} reativado."]);
    }

    /**
     * Métricas gerais do SaaS para o dashboard do admin.
     */
    public function metricas(): JsonResponse
    {
        $totalTenants = Tenant::count();
        $ativos       = Tenant::where('status', 'ativo')->count();

        $assinaturas = \Illuminate\Support\Facades\DB::table('tenant_subscriptions')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $novosUltimos30 = Tenant::where('created_at', '>=', now()->subDays(30))->count();

        return response()->json([
            'tenants' => [
                'total'             => $totalTenants,
                'ativos'            => $ativos,
                'novos_30_dias'     => $novosUltimos30,
            ],
            'assinaturas' => $assinaturas,
        ]);
    }
}
