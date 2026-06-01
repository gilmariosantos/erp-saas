<?php

namespace App\Services\Billing\Gateways;

use App\Models\Invoice;
use App\Models\Tenant;

/**
 * Contrato comum para gateways de pagamento.
 *
 * Permite trocar/combinar Asaas e Mercado Pago sem alterar a lógica de billing.
 * Mesmo padrão Strategy usado nos adaptadores de NFS-e.
 */
interface PaymentGatewayInterface
{
    /**
     * Cria ou recupera o cliente no gateway.
     *
     * @return string ID do cliente no gateway
     */
    public function criarCliente(Tenant $tenant): string;

    /**
     * Cria uma cobrança (PIX, boleto ou cartão).
     *
     * @param  array{metodo: string, valor: float, vencimento: string, descricao: string} $dados
     * @return array{
     *   gateway_invoice_id: string,
     *   link_pagamento: ?string,
     *   pix_copia_cola: ?string,
     *   pix_qrcode: ?string,
     *   linha_digitavel: ?string,
     *   url_boleto: ?string,
     * }
     */
    public function criarCobranca(Tenant $tenant, Invoice $invoice, array $dados): array;

    /**
     * Cria uma assinatura recorrente.
     *
     * @return string ID da assinatura no gateway
     */
    public function criarAssinatura(Tenant $tenant, array $dados): string;

    /**
     * Cancela uma assinatura recorrente.
     */
    public function cancelarAssinatura(string $gatewaySubscriptionId): bool;

    /**
     * Consulta o status de uma cobrança.
     *
     * @return string pendente|pago|vencido|cancelado
     */
    public function consultarCobranca(string $gatewayInvoiceId): string;

    /**
     * Valida a assinatura/autenticidade de um webhook recebido.
     */
    public function validarWebhook(array $headers, string $payload): bool;

    /**
     * Normaliza o payload do webhook para um formato comum.
     *
     * @return array{evento: string, gateway_payment_id: string, status: string, valor: ?float}
     */
    public function parsearWebhook(array $payload): array;
}
