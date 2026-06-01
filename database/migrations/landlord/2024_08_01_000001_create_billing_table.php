<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estrutura de billing (banco central/landlord).
 * Faturas, pagamentos, webhooks dos gateways e controle de uso por tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── Faturas ──────────────────────────────────────────────────────
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()
                  ->constrained('tenant_subscriptions')->nullOnDelete();

            $table->string('numero', 20)->unique();           // INV-2024-00001
            $table->decimal('valor', 10, 2);
            $table->decimal('valor_pago', 10, 2)->default(0);
            $table->date('vencimento');
            $table->date('competencia');                       // mês de referência
            $table->date('pago_em')->nullable();

            $table->enum('status', [
                'pendente', 'pago', 'vencido', 'cancelado', 'estornado',
            ])->default('pendente');

            // Gateway usado
            $table->enum('gateway', ['asaas', 'mercadopago'])->nullable();
            $table->string('gateway_invoice_id')->nullable();  // ID da cobrança no gateway
            $table->string('gateway_payment_id')->nullable();
            $table->enum('metodo_pagamento', ['pix', 'boleto', 'cartao'])->nullable();

            // Links úteis (PIX copia-e-cola, boleto, etc)
            $table->text('link_pagamento')->nullable();
            $table->text('pix_copia_cola')->nullable();
            $table->text('pix_qrcode')->nullable();
            $table->text('linha_digitavel_boleto')->nullable();
            $table->string('url_boleto')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index('vencimento');
            $table->index('gateway_invoice_id');
        });

        // ─── Métodos de pagamento salvos (cartão tokenizado) ──────────────
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->enum('gateway', ['asaas', 'mercadopago']);
            $table->enum('tipo', ['cartao', 'pix', 'boleto'])->default('cartao');
            $table->string('gateway_customer_id')->nullable();  // ID do cliente no gateway
            $table->string('gateway_token')->nullable();         // token do cartão (nunca o número!)
            $table->string('bandeira', 30)->nullable();          // visa, master...
            $table->string('ultimos_digitos', 4)->nullable();
            $table->string('titular', 100)->nullable();
            $table->string('validade', 7)->nullable();           // MM/AAAA
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        // ─── Log de webhooks recebidos (idempotência) ─────────────────────
        Schema::create('billing_webhooks', function (Blueprint $table) {
            $table->id();
            $table->enum('gateway', ['asaas', 'mercadopago']);
            $table->string('evento', 80);                       // PAYMENT_CONFIRMED, etc
            $table->string('gateway_event_id')->nullable()->unique(); // evita duplicação
            $table->json('payload');
            $table->enum('status', ['recebido', 'processado', 'ignorado', 'erro'])->default('recebido');
            $table->text('erro')->nullable();
            $table->timestamp('processado_em')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'evento']);
            $table->index('status');
        });

        // ─── Controle de uso por tenant (para enforcement de limites) ─────
        Schema::create('usage_counters', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('competencia', 7);                   // AAAA-MM
            $table->integer('nfe_emitidas')->default(0);
            $table->integer('nfce_emitidas')->default(0);
            $table->integer('cte_emitidos')->default(0);
            $table->integer('nfse_emitidas')->default(0);
            $table->integer('usuarios_ativos')->default(0);
            $table->decimal('storage_usado_gb', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'competencia']);
        });

        // Adiciona campos de billing à assinatura existente
        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->enum('gateway', ['asaas', 'mercadopago'])->nullable()->after('payment_gateway');
            $table->string('gateway_customer_id')->nullable();
            $table->string('ciclo', 10)->default('mensal');     // mensal | anual
            $table->date('proxima_cobranca')->nullable();
            $table->integer('tentativas_cobranca')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['gateway', 'gateway_customer_id', 'ciclo', 'proxima_cobranca', 'tentativas_cobranca']);
        });
        Schema::dropIfExists('usage_counters');
        Schema::dropIfExists('billing_webhooks');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('invoices');
    }
};
