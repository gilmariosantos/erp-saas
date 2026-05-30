<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usuários super-admin do SaaS (operam no banco landlord/central).
 * Diferentes dos usuários de tenant — estes gerenciam TODAS as empresas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 180)->unique();
            $table->string('password');
            $table->enum('role', ['super_admin', 'suporte', 'financeiro'])->default('suporte');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('admin_personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // Log de provisionamento de tenants (auditoria central)
        Schema::create('tenant_provisioning_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->string('email_responsavel', 180);
            $table->string('razao_social', 150)->nullable();
            $table->string('cnpj', 18)->nullable();
            $table->enum('status', ['iniciado', 'banco_criado', 'migrado', 'seeded', 'concluido', 'falhou'])
                  ->default('iniciado');
            $table->text('erro')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
        });

        // Adiciona campos de controle ao tenant existente (na tabela tenants)
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('email_responsavel', 180)->nullable()->after('id');
            $table->string('razao_social', 150)->nullable()->after('email_responsavel');
            $table->string('cnpj', 18)->nullable()->after('razao_social');
            $table->enum('status', ['provisionando', 'ativo', 'suspenso', 'cancelado'])
                  ->default('provisionando')->after('cnpj');
            $table->timestamp('suspenso_em')->nullable();
            $table->string('motivo_suspensao')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'email_responsavel', 'razao_social', 'cnpj',
                'status', 'suspenso_em', 'motivo_suspensao',
            ]);
        });
        Schema::dropIfExists('tenant_provisioning_logs');
        Schema::dropIfExists('admin_personal_access_tokens');
        Schema::dropIfExists('admin_users');
    }
};
