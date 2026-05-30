<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegistrarTenantRequest;
use App\Models\Tenant;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Http\JsonResponse;

/**
 * @group Onboarding (Central)
 *
 * Auto-registro de novos tenants (self-service).
 * Roda no domínio central, fora do contexto de tenant.
 */
class RegistroController extends Controller
{
    public function __construct(
        private readonly TenantProvisioningService $provisioning,
    ) {}

    /**
     * Registra uma nova empresa e provisiona o tenant com trial.
     */
    public function registrar(RegistrarTenantRequest $request): JsonResponse
    {
        try {
            $tenant = $this->provisioning->provisionar($request->validated());

            return response()->json([
                'message'    => 'Conta criada com sucesso! Seu período de teste de 14 dias começou.',
                'tenant'     => [
                    'id'        => $tenant->id,
                    'url'       => "https://{$tenant->id}." . config('app.domain_base', 'erpsaas.com.br'),
                    'trial_ate' => now()->addDays(14)->format('d/m/Y'),
                ],
            ], 201);

        } catch (\InvalidArgumentException $e) {
            // Erros de validação de negócio (subdomínio em uso, etc)
            return response()->json(['message' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Verifica se um subdomínio está disponível (para validação em tempo real no form).
     */
    public function verificarSubdominio(string $subdominio): JsonResponse
    {
        $reservados = ['www', 'app', 'api', 'admin', 'painel', 'mail', 'staging', 'dev', 'test'];
        $slug = \Illuminate\Support\Str::slug($subdominio);

        $disponivel = strlen($slug) >= 3
            && ! in_array($slug, $reservados)
            && ! Tenant::where('id', $slug)->exists();

        return response()->json([
            'subdominio' => $slug,
            'disponivel' => $disponivel,
            'sugestao'   => $disponivel ? null : $slug . '-' . rand(1, 99),
        ]);
    }
}
