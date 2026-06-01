<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\Billing\Gateways\AsaasGateway;
use App\Services\Billing\Gateways\MercadoPagoGateway;
use App\Services\Billing\Gateways\PaymentGatewayInterface;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Serviço central de billing.
 *
 * Orquestra a criação de faturas, escolha do gateway (Asaas ou Mercado Pago),
 * processamento de webhooks e o ciclo de vida das assinaturas
 * (trial → ativa → vencida → suspensa).
 */
class BillingService
{
    /** Gateways disponíveis */
    private const GATEWAYS = [
        'asaas'       => AsaasGateway::class,
        'mercadopago' => MercadoPagoGateway::class,
    ];

    public function __construct(
        private readonly TenantProvisioningService $provisioning,
    ) {}

    /**
     * Resolve o adaptador do gateway pelo nome.
     */
    public function gateway(string $nome): PaymentGatewayInterface
    {
        $classe = self::GATEWAYS[$nome] ?? null;
        if (! $classe) {
            throw new \InvalidArgumentException("Gateway de pagamento não suportado: {$nome}");
        }
        return app($classe);
    }

    /**
     * Gera uma fatura para a assinatura e cria a cobrança no gateway escolhido.
     *
     * @param string $gatewayNome  asaas|mercadopago
     * @param string $metodo       pix|boleto|cartao
     */
    public function gerarFatura(
        Tenant $tenant,
        string $gatewayNome,
        string $metodo,
        ?string $competencia = null,
    ): Invoice {
        $subscription = $tenant->subscription;
        if (! $subscription) {
            throw new \RuntimeException('Tenant não possui assinatura ativa.');
        }

        $plano = DB::table('plans')->where('id', $subscription->plan_id)->first();
        $valor = $subscription->ciclo === 'anual'
            ? $plano->price_annual
            : $plano->price_monthly;

        $competencia ??= now()->format('Y-m');

        return DB::transaction(function () use ($tenant, $subscription, $gatewayNome, $metodo, $valor, $competencia, $plano) {
            // Cria a fatura local
            $invoice = Invoice::create([
                'tenant_id'       => $tenant->id,
                'subscription_id' => $subscription->id,
                'numero'          => $this->gerarNumeroFatura(),
                'valor'           => $valor,
                'vencimento'      => now()->addDays(5)->toDateString(),
                'competencia'     => $competencia . '-01',
                'status'          => 'pendente',
                'gateway'         => $gatewayNome,
                'metodo_pagamento'=> $metodo,
            ]);

            // Cria a cobrança no gateway
            $gateway = $this->gateway($gatewayNome);
            $cobranca = $gateway->criarCobranca($tenant, $invoice, [
                'metodo'     => $metodo,
                'valor'      => $valor,
                'vencimento' => $invoice->vencimento->toDateString(),
                'descricao'  => "Assinatura {$plano->name} — {$competencia}",
            ]);

            $invoice->update([
                'gateway_invoice_id'      => $cobranca['gateway_invoice_id'],
                'link_pagamento'          => $cobranca['link_pagamento'],
                'pix_copia_cola'          => $cobranca['pix_copia_cola'],
                'pix_qrcode'              => $cobranca['pix_qrcode'],
                'linha_digitavel_boleto'  => $cobranca['linha_digitavel'],
                'url_boleto'              => $cobranca['url_boleto'],
            ]);

            Log::info('Fatura gerada', [
                'invoice_id' => $invoice->id,
                'tenant'     => $tenant->id,
                'gateway'    => $gatewayNome,
                'metodo'     => $metodo,
                'valor'      => $valor,
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * Processa um webhook de pagamento recebido de qualquer gateway.
     * Idempotente — ignora eventos já processados.
     */
    public function processarWebhook(string $gatewayNome, array $payload): void
    {
        $gateway = $this->gateway($gatewayNome);
        $dados = $gateway->parsearWebhook($payload);

        // Idempotência: ignora se já processamos este evento
        $jaProcessado = DB::table('billing_webhooks')
            ->where('gateway_event_id', $dados['gateway_event_id'])
            ->where('status', 'processado')
            ->exists();

        $webhookLog = DB::table('billing_webhooks')->insertGetId([
            'gateway'          => $gatewayNome,
            'evento'           => $dados['evento'],
            'gateway_event_id' => $dados['gateway_event_id'],
            'payload'          => json_encode($payload),
            'status'           => 'recebido',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        if ($jaProcessado) {
            DB::table('billing_webhooks')->where('id', $webhookLog)->update(['status' => 'ignorado']);
            return;
        }

        try {
            if ($dados['status'] === 'pago') {
                $this->confirmarPagamento($dados['gateway_payment_id']);
            } elseif (in_array($dados['status'], ['vencido', 'cancelado'])) {
                $this->tratarFalhaPagamento($dados['gateway_payment_id'], $dados['status']);
            }

            DB::table('billing_webhooks')->where('id', $webhookLog)
                ->update(['status' => 'processado', 'processado_em' => now()]);

        } catch (\Throwable $e) {
            DB::table('billing_webhooks')->where('id', $webhookLog)
                ->update(['status' => 'erro', 'erro' => $e->getMessage()]);
            Log::error('Erro ao processar webhook de billing', [
                'gateway' => $gatewayNome,
                'erro'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Confirma o pagamento de uma fatura e reativa o tenant se estava suspenso.
     */
    public function confirmarPagamento(string $gatewayPaymentId): void
    {
        $invoice = Invoice::where('gateway_invoice_id', $gatewayPaymentId)->first();
        if (! $invoice || $invoice->status === 'pago') return;

        DB::transaction(function () use ($invoice) {
            $invoice->update([
                'status'     => 'pago',
                'valor_pago' => $invoice->valor,
                'pago_em'    => now(),
            ]);

            $subscription = $invoice->subscription;
            if ($subscription) {
                $proxima = $subscription->ciclo === 'anual'
                    ? now()->addYear()
                    : now()->addMonth();

                $subscription->update([
                    'status'              => 'active',
                    'current_period_start'=> now()->toDateString(),
                    'current_period_end'  => $proxima->toDateString(),
                    'proxima_cobranca'    => $proxima->toDateString(),
                    'tentativas_cobranca' => 0,
                ]);
            }

            // Reativa o tenant se estava suspenso por inadimplência
            $tenant = Tenant::find($invoice->tenant_id);
            if ($tenant && $tenant->isSuspenso()) {
                $this->provisioning->reativar($tenant);
            }

            Log::info('Pagamento confirmado', ['invoice_id' => $invoice->id, 'tenant' => $invoice->tenant_id]);
        });
    }

    /**
     * Trata falha de pagamento — incrementa tentativas e suspende após 3 falhas.
     */
    public function tratarFalhaPagamento(string $gatewayPaymentId, string $status): void
    {
        $invoice = Invoice::where('gateway_invoice_id', $gatewayPaymentId)->first();
        if (! $invoice) return;

        $invoice->update(['status' => $status === 'vencido' ? 'vencido' : 'cancelado']);

        $subscription = $invoice->subscription;
        if (! $subscription) return;

        $tentativas = $subscription->tentativas_cobranca + 1;
        $subscription->update([
            'tentativas_cobranca' => $tentativas,
            'status'              => 'past_due',
        ]);

        // Após 3 tentativas falhas, suspende o tenant
        if ($tentativas >= 3) {
            $tenant = Tenant::find($invoice->tenant_id);
            if ($tenant) {
                $this->provisioning->suspender($tenant, 'Inadimplência: 3 tentativas de cobrança falharam.');
                $subscription->update(['status' => 'suspended']);
            }
        }
    }

    /**
     * Cancela a assinatura de um tenant.
     */
    public function cancelarAssinatura(Tenant $tenant): void
    {
        $subscription = $tenant->subscription;
        if (! $subscription) return;

        if ($subscription->gateway_subscription_id && $subscription->gateway) {
            $this->gateway($subscription->gateway)
                ->cancelarAssinatura($subscription->gateway_subscription_id);
        }

        $subscription->update([
            'status'       => 'cancelled',
            'cancelled_at' => now()->toDateString(),
        ]);
    }

    private function gerarNumeroFatura(): string
    {
        $ano = now()->year;
        $ultimo = Invoice::whereYear('created_at', $ano)->count();
        return sprintf('INV-%d-%05d', $ano, $ultimo + 1);
    }
}
