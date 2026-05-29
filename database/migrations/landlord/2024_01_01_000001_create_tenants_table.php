<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela central de tenants (empresas/clientes do SaaS).
 * Cada tenant terá seu próprio banco de dados isolado.
 *
 * @see https://tenancyforlaravel.com/docs/v3/
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();          // slug único ex: "empresa-abc"
            $table->timestamps();
            $table->json('data')->nullable();          // stancl/tenancy usa este campo
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->increments('id');
            $table->string('domain', 253)->unique();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);               // Básico, Pro, Enterprise
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_annual', 10, 2)->default(0);
            $table->integer('max_users')->default(1);
            $table->integer('max_empresas')->default(1);
            $table->integer('max_nfe_mes')->default(50);
            $table->integer('max_cte_mes')->default(10);
            $table->integer('storage_gb')->default(5);
            $table->json('features')->nullable();       // features habilitadas
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['trial', 'active', 'suspended', 'cancelled', 'past_due'])
                  ->default('trial');
            $table->date('trial_ends_at')->nullable();
            $table->date('current_period_start')->nullable();
            $table->date('current_period_end')->nullable();
            $table->date('cancelled_at')->nullable();
            $table->string('payment_gateway')->nullable();    // stripe, pagseguro, etc
            $table->string('gateway_subscription_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('tenants');
    }
};
