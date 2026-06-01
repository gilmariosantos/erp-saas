<?php
namespace App\Http\Controllers\Tenant\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\BillingService;
use App\Services\Billing\UsageLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Assinatura (Tenant)
 * Gestão da assinatura pelo próprio cliente.
 */
class AssinaturaController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly UsageLimitService $usage,
    ) {}

    /** Status atual da assinatura + uso vs limites. */
    public function status(Request $request): JsonResponse
    {
        $tenant = tenant();
        return response()->json([
            'assinatura' => $tenant->subscription,
            'uso'        => $this->usage->resumoUso($tenant),
        ]);
    }

    /** Lista as faturas do tenant. */
    public function faturas(): JsonResponse
    {
        $faturas = \App\Models\Invoice::where('tenant_id', tenant('id'))
            ->orderByDesc('created_at')->paginate(12);
        return response()->json($faturas);
    }

    /** Gera uma nova fatura/cobrança. */
    public function gerarCobranca(Request $request): JsonResponse
    {
        $request->validate([
            'gateway' => ['required', 'in:asaas,mercadopago'],
            'metodo'  => ['required', 'in:pix,boleto,cartao'],
        ]);

        try {
            $invoice = $this->billing->gerarFatura(
                tenant(),
                $request->string('gateway'),
                $request->string('metodo'),
            );
            return response()->json([
                'message' => 'Cobrança gerada com sucesso.',
                'fatura'  => $invoice,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** Cancela a assinatura. */
    public function cancelar(): JsonResponse
    {
        $this->billing->cancelarAssinatura(tenant());
        return response()->json(['message' => 'Assinatura cancelada. Você terá acesso até o fim do período pago.']);
    }
}
