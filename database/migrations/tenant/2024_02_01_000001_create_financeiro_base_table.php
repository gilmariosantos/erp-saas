<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estrutura base do módulo financeiro:
 * - Contas bancárias
 * - Plano de contas (DRE/Balanço)
 * - Centros de custo
 * - Formas de pagamento
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── Contas bancárias ─────────────────────────────────────────────
        Schema::create('contas_bancarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->string('nome', 100);                         // "Caixa Geral", "Bradesco PJ"
            $table->enum('tipo', ['corrente', 'poupanca', 'caixa', 'investimento', 'cartao'])
                  ->default('corrente');
            $table->string('banco_codigo', 10)->nullable();      // código BACEN: 237, 341...
            $table->string('banco_nome', 80)->nullable();
            $table->string('agencia', 10)->nullable();
            $table->string('agencia_digito', 2)->nullable();
            $table->string('conta', 20)->nullable();
            $table->string('conta_digito', 2)->nullable();
            $table->string('pix_chave', 180)->nullable();
            $table->enum('pix_tipo', ['cpf','cnpj','email','telefone','aleatoria'])->nullable();
            $table->decimal('saldo_inicial', 15, 2)->default(0);
            $table->date('saldo_inicial_data')->nullable();
            $table->decimal('saldo_atual', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('exibir_dashboard')->default(true);
            $table->string('cor', 7)->nullable();               // hex para UI
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'is_active']);
        });

        // ─── Plano de contas ──────────────────────────────────────────────
        Schema::create('plano_contas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();              // 1.1.01, 2.2.03...
            $table->string('nome', 150);
            $table->enum('tipo', ['receita', 'despesa', 'ativo', 'passivo', 'patrimonio'])
                  ->default('despesa');
            $table->enum('natureza', ['devedora', 'credora'])->default('devedora');
            $table->foreignId('parent_id')->nullable()
                  ->constrained('plano_contas')->nullOnDelete();
            $table->integer('nivel')->default(1);
            $table->boolean('aceita_lancamento')->default(true); // false = apenas agrupadora
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('codigo');
            $table->index(['tipo', 'is_active']);
        });

        // ─── Centros de custo ─────────────────────────────────────────────
        Schema::create('centros_custo', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nome', 100);
            $table->foreignId('parent_id')->nullable()
                  ->constrained('centros_custo')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // ─── Formas de pagamento ──────────────────────────────────────────
        Schema::create('formas_pagamento', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 80);
            $table->enum('tipo', [
                'dinheiro', 'pix', 'boleto', 'cartao_debito',
                'cartao_credito', 'transferencia', 'cheque', 'outros'
            ])->default('dinheiro');
            $table->integer('prazo_compensacao')->default(0);    // dias úteis
            $table->decimal('taxa_percentual', 6, 4)->default(0);
            $table->decimal('taxa_fixa', 10, 2)->default(0);
            $table->boolean('gera_boleto')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formas_pagamento');
        Schema::dropIfExists('centros_custo');
        Schema::dropIfExists('plano_contas');
        Schema::dropIfExists('contas_bancarias');
    }
};
