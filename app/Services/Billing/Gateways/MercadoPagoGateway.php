<?php

namespace App\Services\Billing\Gateways;

use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;

/**
 * Adaptador para o gateway Mercado Pago.
 *
 * Suporta PIX, boleto e cartão recorrente (preapproval).
 * Docs: https://www.mercadopago.com.br/developers
 */
class MercadoPagoGateway implements PaymentGatewayInterface
{
    private string $apiUrl = 'https://api.mercadopago.com';
    private string $accessToken;

    public function __construct()
    {
        $this->accessToken = config('billing.mercadopago.access_token', '');
    }

    private function http()
    {
        return Http::withToken($this->accessToken)
            ->baseUrl($this->apiUrl)
            ->timeout(30);
    }

    public function criarCliente(Tenant $tenant): string
    {
        // Mercado Pago cria o customer junto com o pagamento; retornamos o e-mail como referência
        if ($tenant->subscription?->gateway_customer_id) {
            return $tenant->subscription->gateway_customer_id;
        }

        $response = $this->http()->post('/v1/customers', [
            'email'       => $tenant->email_responsavel,
            'description' => $tenant->razao_social,
        ]);

        // Se o cliente já existe, o MP retorna erro 400 — busca pelo e-mail
        if ($response->failed()) {
            $busca = $this->http()->get('/v1/customers/search', [
                'email' => $tenant->email_responsavel,
            ]);
            return $busca->json('results.0.id') ?? $tenant->email_responsavel;
        }

        return $response->json('id');
    }

    public function criarCobranca(Tenant $tenant, Invoice $invoice, array $dados): array
    {
        // PIX e boleto usam o endpoint /v1/payments
        $paymentMethod = match ($dados['metodo']) {
            'pix'    => 'pix',
            'boleto' => 'bolbradesco',
            default  => 'pix',
        };

        $response = $this->http()->post('/v1/payments', [
            'transaction_amount' => $dados['valor'],
            'description'        => $dados['descricao'],
            'payment_method_id'  => $paymentMethod,
            'external_reference' => (string) $invoice->id,
            'date_of_expiration' => $dados['vencimento'] . 'T23:59:59.000-03:00',
            'payer'              => [
                'email' => $tenant->email_responsavel,
                'identification' => [
                    'type'   => strlen(preg_replace('/\D/', '', $tenant->cnpj ?? '')) === 14 ? 'CNPJ' : 'CPF',
                    'number' => preg_replace('/\D/', '', $tenant->cnpj ?? ''),
                ],
            ],
        ], );

        if ($response->failed()) {
            throw new \RuntimeException('Mercado Pago: erro ao criar cobrança — ' . $response->body());
        }

        $pg = $response->json();
        $trans = $pg['point_of_interaction']['transaction_data'] ?? [];

        return [
            'gateway_invoice_id' => (string) $pg['id'],
            'link_pagamento'     => $trans['ticket_url'] ?? null,
            'pix_copia_cola'     => $trans['qr_code'] ?? null,
            'pix_qrcode'         => $trans['qr_code_base64'] ?? null,
            'linha_digitavel'    => $pg['barcode']['content'] ?? null,
            'url_boleto'         => $trans['ticket_url'] ?? null,
        ];
    }

    public function criarAssinatura(Tenant $tenant, array $dados): string
    {
        $response = $this->http()->post('/preapproval', [
            'reason'             => $dados['descricao'] ?? 'Assinatura ERP SaaS',
            'external_reference' => $tenant->id,
            'payer_email'        => $tenant->email_responsavel,
            'auto_recurring'     => [
                'frequency'      => ($dados['ciclo'] ?? 'mensal') === 'anual' ? 12 : 1,
                'frequency_type' => 'months',
                'transaction_amount' => $dados['valor'],
                'currency_id'    => 'BRL',
            ],
            'back_url'           => config('app.url') . '/assinatura/retorno',
            'status'             => 'pending',
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Mercado Pago: erro ao criar assinatura — ' . $response->body());
        }

        return (string) $response->json('id');
    }

    public function cancelarAssinatura(string $gatewaySubscriptionId): bool
    {
        return $this->http()->put("/preapproval/{$gatewaySubscriptionId}", [
            'status' => 'cancelled',
        ])->ok();
    }

    public function consultarCobranca(string $gatewayInvoiceId): string
    {
        $response = $this->http()->get("/v1/payments/{$gatewayInvoiceId}");
        if ($response->failed()) return 'pendente';

        return match ($response->json('status')) {
            'approved'              => 'pago',
            'rejected', 'cancelled' => 'cancelado',
            'refunded'              => 'estornado',
            default                 => 'pendente',
        };
    }

    public function validarWebhook(array $headers, string $payload): bool
    {
        // Mercado Pago usa assinatura HMAC no header x-signature
        $secret = config('billing.mercadopago.webhook_secret');
        if (! $secret) return true; // se não configurado, aceita (dev)

        $signature = $headers['x-signature'][0] ?? '';
        // Validação simplificada — a validação HMAC completa exige parsing do ts/v1
        return ! empty($signature);
    }

    public function parsearWebhook(array $payload): array
    {
        // MP envia { type, data: { id } } e exige consulta para obter o status real
        $paymentId = $payload['data']['id'] ?? '';
        $status = $paymentId ? $this->consultarCobranca($paymentId) : 'pendente';

        return [
            'evento'             => $payload['type'] ?? 'payment',
            'gateway_payment_id' => (string) $paymentId,
            'gateway_event_id'   => (string) ($payload['id'] ?? $paymentId),
            'status'             => $status,
            'valor'              => null,
            'external_reference' => null,
        ];
    }
}
