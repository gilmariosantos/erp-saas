<?php

namespace App\Services\Billing\Gateways;

use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adaptador para o gateway Asaas (asaas.com).
 *
 * Suporta PIX, boleto e cartão de crédito recorrente.
 * Docs: https://docs.asaas.com/
 */
class AsaasGateway implements PaymentGatewayInterface
{
    private string $apiUrl;
    private string $apiKey;

    public function __construct()
    {
        $ambiente = config('billing.asaas.ambiente', 'sandbox');
        $this->apiUrl = $ambiente === 'producao'
            ? 'https://api.asaas.com/v3'
            : 'https://api-sandbox.asaas.com/v3';
        $this->apiKey = config('billing.asaas.api_key', '');
    }

    private function http()
    {
        return Http::withHeaders([
            'access_token' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->baseUrl($this->apiUrl)->timeout(30);
    }

    public function criarCliente(Tenant $tenant): string
    {
        // Reaproveita cliente se já existe
        if ($tenant->subscription?->gateway_customer_id) {
            return $tenant->subscription->gateway_customer_id;
        }

        $response = $this->http()->post('/customers', [
            'name'                 => $tenant->razao_social,
            'cpfCnpj'              => preg_replace('/\D/', '', $tenant->cnpj ?? ''),
            'email'                => $tenant->email_responsavel,
            'externalReference'    => $tenant->id,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Asaas: erro ao criar cliente — ' . $response->body());
        }

        return $response->json('id');
    }

    public function criarCobranca(Tenant $tenant, Invoice $invoice, array $dados): array
    {
        $customerId = $this->criarCliente($tenant);

        $billingType = match ($dados['metodo']) {
            'pix'    => 'PIX',
            'boleto' => 'BOLETO',
            'cartao' => 'CREDIT_CARD',
            default  => 'UNDEFINED',
        };

        $response = $this->http()->post('/payments', [
            'customer'          => $customerId,
            'billingType'       => $billingType,
            'value'             => $dados['valor'],
            'dueDate'           => $dados['vencimento'],
            'description'       => $dados['descricao'],
            'externalReference' => $invoice->id,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Asaas: erro ao criar cobrança — ' . $response->body());
        }

        $pagamento = $response->json();
        $resultado = [
            'gateway_invoice_id' => $pagamento['id'],
            'link_pagamento'     => $pagamento['invoiceUrl'] ?? null,
            'pix_copia_cola'     => null,
            'pix_qrcode'         => null,
            'linha_digitavel'    => $pagamento['identificationField'] ?? null,
            'url_boleto'         => $pagamento['bankSlipUrl'] ?? null,
        ];

        // Busca o QR code do PIX se for pagamento PIX
        if ($dados['metodo'] === 'pix') {
            $pix = $this->http()->get("/payments/{$pagamento['id']}/pixQrCode");
            if ($pix->ok()) {
                $resultado['pix_copia_cola'] = $pix->json('payload');
                $resultado['pix_qrcode']     = $pix->json('encodedImage');
            }
        }

        return $resultado;
    }

    public function criarAssinatura(Tenant $tenant, array $dados): string
    {
        $customerId = $this->criarCliente($tenant);

        $response = $this->http()->post('/subscriptions', [
            'customer'    => $customerId,
            'billingType' => strtoupper($dados['metodo'] ?? 'UNDEFINED'),
            'value'       => $dados['valor'],
            'nextDueDate' => $dados['proxima_cobranca'],
            'cycle'       => ($dados['ciclo'] ?? 'mensal') === 'anual' ? 'YEARLY' : 'MONTHLY',
            'description' => $dados['descricao'] ?? 'Assinatura ERP SaaS',
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Asaas: erro ao criar assinatura — ' . $response->body());
        }

        return $response->json('id');
    }

    public function cancelarAssinatura(string $gatewaySubscriptionId): bool
    {
        return $this->http()->delete("/subscriptions/{$gatewaySubscriptionId}")->ok();
    }

    public function consultarCobranca(string $gatewayInvoiceId): string
    {
        $response = $this->http()->get("/payments/{$gatewayInvoiceId}");
        if ($response->failed()) return 'pendente';

        return match ($response->json('status')) {
            'CONFIRMED', 'RECEIVED', 'RECEIVED_IN_CASH' => 'pago',
            'OVERDUE'                                   => 'vencido',
            'REFUNDED', 'REFUND_REQUESTED'              => 'estornado',
            'DELETED'                                   => 'cancelado',
            default                                     => 'pendente',
        };
    }

    public function validarWebhook(array $headers, string $payload): bool
    {
        // Asaas valida via token configurado no header asaas-access-token
        $tokenEsperado = config('billing.asaas.webhook_token');
        $tokenRecebido = $headers['asaas-access-token'][0] ?? $headers['Asaas-Access-Token'][0] ?? null;

        return $tokenEsperado && hash_equals($tokenEsperado, (string) $tokenRecebido);
    }

    public function parsearWebhook(array $payload): array
    {
        $payment = $payload['payment'] ?? [];

        $status = match ($payload['event'] ?? '') {
            'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED' => 'pago',
            'PAYMENT_OVERDUE'                       => 'vencido',
            'PAYMENT_REFUNDED'                      => 'estornado',
            'PAYMENT_DELETED'                       => 'cancelado',
            default                                 => 'pendente',
        };

        return [
            'evento'             => $payload['event'] ?? 'desconhecido',
            'gateway_payment_id' => $payment['id'] ?? '',
            'gateway_event_id'   => $payload['id'] ?? ($payment['id'] ?? '') . '-' . ($payload['event'] ?? ''),
            'status'             => $status,
            'valor'              => isset($payment['value']) ? (float) $payment['value'] : null,
            'external_reference' => $payment['externalReference'] ?? null,
        ];
    }
}
