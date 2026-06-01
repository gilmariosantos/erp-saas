<?php

use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\Billing\BillingService;
use App\Services\Billing\Gateways\AsaasGateway;
use App\Services\Billing\Gateways\MercadoPagoGateway;
use App\Services\Billing\UsageLimitService;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// ─── Resolução de gateway ──────────────────────────────────────────────────────

describe('BillingService::gateway()', function () {

    it('resolve o gateway Asaas', function () {
        $service = app(BillingService::class);
        expect($service->gateway('asaas'))->toBeInstanceOf(AsaasGateway::class);
    });

    it('resolve o gateway Mercado Pago', function () {
        $service = app(BillingService::class);
        expect($service->gateway('mercadopago'))->toBeInstanceOf(MercadoPagoGateway::class);
    });

    it('lança exceção para gateway não suportado', function () {
        $service = app(BillingService::class);
        expect(fn () => $service->gateway('paypal'))
            ->toThrow(\InvalidArgumentException::class, 'não suportado');
    });

});

// ─── Parsing de webhook ────────────────────────────────────────────────────────

describe('AsaasGateway::parsearWebhook()', function () {

    it('identifica pagamento confirmado como pago', function () {
        $gateway = new AsaasGateway();
        $resultado = $gateway->parsearWebhook([
            'id'    => 'evt_123',
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => ['id' => 'pay_456', 'value' => 99.90, 'externalReference' => '1'],
        ]);

        expect($resultado['status'])->toBe('pago')
            ->and($resultado['gateway_payment_id'])->toBe('pay_456')
            ->and($resultado['valor'])->toBe(99.90);
    });

    it('identifica pagamento vencido', function () {
        $gateway = new AsaasGateway();
        $resultado = $gateway->parsearWebhook([
            'id'    => 'evt_789',
            'event' => 'PAYMENT_OVERDUE',
            'payment' => ['id' => 'pay_999'],
        ]);

        expect($resultado['status'])->toBe('vencido');
    });

});

// ─── Validação de webhook ──────────────────────────────────────────────────────

describe('AsaasGateway::validarWebhook()', function () {

    it('aceita webhook com token correto', function () {
        config(['billing.asaas.webhook_token' => 'token-secreto-123']);
        $gateway = new AsaasGateway();

        $valido = $gateway->validarWebhook(
            ['asaas-access-token' => ['token-secreto-123']],
            '{}'
        );

        expect($valido)->toBeTrue();
    });

    it('rejeita webhook com token incorreto', function () {
        config(['billing.asaas.webhook_token' => 'token-secreto-123']);
        $gateway = new AsaasGateway();

        $valido = $gateway->validarWebhook(
            ['asaas-access-token' => ['token-errado']],
            '{}'
        );

        expect($valido)->toBeFalse();
    });

});

// ─── Controle de limites ───────────────────────────────────────────────────────

describe('UsageLimitService', function () {

    function criarTenantComPlano(int $maxNfe): Tenant
    {
        DB::table('plans')->insert([
            'id' => 1, 'name' => 'Pro', 'slug' => 'pro',
            'price_monthly' => 249.90, 'price_annual' => 2499,
            'max_users' => 10, 'max_empresas' => 3,
            'max_nfe_mes' => $maxNfe, 'max_cte_mes' => 100, 'storage_gb' => 20,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $tenant = Tenant::create([
            'id' => 'teste-limite', 'razao_social' => 'Teste', 'status' => 'ativo',
            'email_responsavel' => 'a@b.com',
        ]);

        TenantSubscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => 1, 'status' => 'active',
            'ciclo' => 'mensal',
        ]);

        return $tenant->fresh();
    }

    it('libera tudo durante o trial', function () {
        DB::table('plans')->insert([
            'id' => 1, 'name' => 'Pro', 'slug' => 'pro', 'price_monthly' => 99,
            'price_annual' => 999, 'max_users' => 5, 'max_empresas' => 1,
            'max_nfe_mes' => 1, 'max_cte_mes' => 1, 'storage_gb' => 5,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $tenant = Tenant::create(['id' => 'trial-t', 'razao_social' => 'T', 'status' => 'ativo', 'email_responsavel' => 'a@b.com']);
        TenantSubscription::create(['tenant_id' => $tenant->id, 'plan_id' => 1, 'status' => 'trial', 'ciclo' => 'mensal']);

        $service = new UsageLimitService();
        expect($service->verificarLimite($tenant->fresh(), 'nfe'))->toBeTrue();
    });

    it('permite emissão dentro do limite', function () {
        $tenant = criarTenantComPlano(100);
        $service = new UsageLimitService();

        expect($service->verificarLimite($tenant, 'nfe'))->toBeTrue();
    });

    it('bloqueia emissão ao atingir o limite', function () {
        $tenant = criarTenantComPlano(5);
        $service = new UsageLimitService();

        // Simula 5 NF-es já emitidas
        DB::table('usage_counters')->insert([
            'tenant_id' => $tenant->id,
            'competencia' => now()->format('Y-m'),
            'nfe_emitidas' => 5,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        expect(fn () => $service->verificarLimite($tenant, 'nfe'))
            ->toThrow(\App\Exceptions\LimiteExcedidoException::class, 'Limite do plano atingido');
    });

    it('registra uso incrementando o contador', function () {
        $tenant = criarTenantComPlano(100);
        $service = new UsageLimitService();

        $service->registrarUso($tenant, 'nfe');
        $service->registrarUso($tenant, 'nfe');

        expect($service->usoAtual($tenant)->nfe_emitidas)->toBe(2);
    });

    it('plano enterprise (9999) é ilimitado', function () {
        $tenant = criarTenantComPlano(9999);
        $service = new UsageLimitService();

        DB::table('usage_counters')->insert([
            'tenant_id' => $tenant->id, 'competencia' => now()->format('Y-m'),
            'nfe_emitidas' => 50000, 'created_at' => now(), 'updated_at' => now(),
        ]);

        expect($service->verificarLimite($tenant, 'nfe'))->toBeTrue();
    });

});
