<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de Estoque.
 * Suporta múltiplos locais de estoque, lotes, rastreabilidade e inventário.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── Locais de estoque (almoxarifados/depósitos) ──────────────────
        Schema::create('locais_estoque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->string('nome', 100);
            $table->string('descricao', 200)->nullable();
            $table->boolean('is_padrao')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'is_active']);
        });

        // ─── Lotes de produtos ────────────────────────────────────────────
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained()->cascadeOnDelete();
            $table->string('numero', 60);
            $table->date('data_fabricacao')->nullable();
            $table->date('data_validade')->nullable();
            $table->string('fornecedor_lote', 100)->nullable();
            $table->decimal('quantidade_inicial', 15, 4)->default(0);
            $table->decimal('quantidade_atual', 15, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['produto_id', 'numero']);
            $table->index('data_validade');
        });

        // ─── Movimentações de estoque ─────────────────────────────────────
        Schema::create('movimentacoes_estoque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->foreignId('produto_id')->constrained()->restrictOnDelete();
            $table->foreignId('local_estoque_id')->nullable()
                  ->constrained('locais_estoque')->nullOnDelete();
            $table->foreignId('lote_id')->nullable()
                  ->constrained('lotes')->nullOnDelete();

            $table->enum('tipo', [
                'entrada',        // compra, devolução de venda, ajuste+
                'saida',          // venda, devolução de compra, ajuste-
                'transferencia',  // entre locais
                'inventario',     // acerto de inventário
                'producao',       // entrada de produção
                'perda',          // quebra, vencimento, sinistro
            ]);

            $table->enum('origem_tipo', [
                'nota_entrada', 'nota_saida', 'pedido_venda', 'pedido_compra',
                'ajuste', 'inventario', 'transferencia', 'producao', 'manual',
            ])->nullable();
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->string('origem_descricao', 200)->nullable();

            $table->decimal('quantidade', 15, 4);
            $table->decimal('custo_unitario', 15, 4)->default(0);
            $table->decimal('custo_total', 15, 4)
                  ->storedAs('quantidade * custo_unitario');

            // Saldo após movimento (snapshot para auditoria)
            $table->decimal('saldo_anterior', 15, 4)->default(0);
            $table->decimal('saldo_posterior', 15, 4)->default(0);

            $table->date('data_movimento');
            $table->text('observacao')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['empresa_id', 'produto_id', 'data_movimento']);
            $table->index(['origem_tipo', 'origem_id']);
            $table->index('data_movimento');
        });

        // ─── Pedidos de compra ────────────────────────────────────────────
        Schema::create('pedidos_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->string('numero', 20)->nullable();
            $table->foreignId('fornecedor_id')->constrained('pessoas')->restrictOnDelete();
            $table->foreignId('local_estoque_id')->nullable()
                  ->constrained('locais_estoque')->nullOnDelete();

            $table->date('data_pedido');
            $table->date('data_previsao_entrega')->nullable();
            $table->date('data_recebimento')->nullable();

            $table->enum('status', [
                'rascunho', 'enviado', 'confirmado',
                'parcial', 'recebido', 'cancelado',
            ])->default('rascunho');

            $table->decimal('total_produtos', 15, 2)->default(0);
            $table->decimal('total_frete', 15, 2)->default(0);
            $table->decimal('total_desconto', 15, 2)->default(0);
            $table->decimal('total_pedido', 15, 2)->default(0);

            $table->text('observacao')->nullable();
            $table->string('numero_cotacao', 60)->nullable();
            $table->string('condicao_pagamento', 100)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'status']);
            $table->index('data_pedido');
        });

        Schema::create('pedido_compra_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_compra_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained()->restrictOnDelete();
            $table->smallInteger('numero_item');
            $table->decimal('quantidade', 15, 4);
            $table->decimal('quantidade_recebida', 15, 4)->default(0);
            $table->decimal('preco_unitario', 15, 4);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->date('data_entrega_prevista')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index(['pedido_compra_id', 'numero_item']);
        });

        // ─── Inventários ──────────────────────────────────────────────────
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->foreignId('local_estoque_id')->nullable()
                  ->constrained('locais_estoque')->nullOnDelete();
            $table->string('descricao', 200);
            $table->date('data_inventario');
            $table->enum('status', ['aberto', 'contagem', 'revisao', 'finalizado', 'cancelado'])
                  ->default('aberto');
            $table->timestamp('iniciado_em')->nullable();
            $table->timestamp('finalizado_em')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inventario_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventario_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained()->restrictOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->nullOnDelete();
            $table->decimal('quantidade_sistema', 15, 4)->default(0);
            $table->decimal('quantidade_contada', 15, 4)->nullable();
            $table->decimal('diferenca', 15, 4)
                  ->storedAs('COALESCE(quantidade_contada, 0) - quantidade_sistema');
            $table->decimal('custo_unitario', 15, 4)->default(0);
            $table->boolean('contado')->default(false);
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->unique(['inventario_id', 'produto_id', 'lote_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_itens');
        Schema::dropIfExists('inventarios');
        Schema::dropIfExists('pedido_compra_itens');
        Schema::dropIfExists('pedidos_compra');
        Schema::dropIfExists('movimentacoes_estoque');
        Schema::dropIfExists('lotes');
        Schema::dropIfExists('locais_estoque');
    }
};
