<?php
namespace App\Http\Controllers\Central\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group Billing Webhooks (Central)
 *
 * Recebe notificações de pagamento dos gateways.
 * IMPORTANTE: valida a autenticidade antes de processar (anti-fraude).
 */
class WebhookController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function asaas(Request $request): JsonResponse
    {
        return $this->processar('asaas', $request);
    }

    public function mercadopago(Request $request): JsonResponse
    {
        return $this->processar('mercadopago', $request);
    }

    private function processar(string $gatewayNome, Request $request): JsonResponse
    {
        $gateway = $this->billing->gateway($gatewayNome);

        // Valida autenticidade do webhook (proteção contra requisições forjadas)
        if (! $gateway->validarWebhook($request->headers->all(), $request->getContent())) {
            Log::warning("Webhook {$gatewayNome} com assinatura inválida rejeitado", ['ip' => $request->ip()]);
            return response()->json(['message' => 'Assinatura inválida'], 401);
        }

        try {
            $this->billing->processarWebhook($gatewayNome, $request->all());
            return response()->json(['received' => true]);
        } catch (\Throwable $e) {
            // Retorna 200 mesmo em erro para o gateway não ficar reenviando indefinidamente;
            // o erro fica registrado em billing_webhooks para reprocessamento manual.
            return response()->json(['received' => true, 'error' => 'logged']);
        }
    }
}
