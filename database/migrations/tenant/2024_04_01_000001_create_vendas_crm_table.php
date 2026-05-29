<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de Vendas e CRM.
 * Pedidos, orçamentos, pipeline de oportunidades e comissões.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── Pipeline CRM ─────────────────────────────────────────────────
        Schema::create('funis_venda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->string('nome', 100);
            $table->boolean('is_padrao')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('etapas_funil', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funil_id')->constrained('funis_venda')->cascadeOnDelete();
            $table->string('nome', 80);
            $table->string('cor', 7)->default('#3B82F6');
            $table->integer('ordem')->default(0);
            $table->decimal('probabilidade', 5, 2)->default(0);
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->timestamps();
        });

        Schema::create('oportunidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->foreignId('funil_id')->constrained('funis_venda')->restrictOnDelete();
            $table->foreignId('etapa_id')->constrained('etapas_funil')->restrictOnDelete();
            $table->foreignId('pessoa_id')->constrained('pessoas')->restrictOnDelete();
            $table->foreignId('responsavel_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titulo', 200);
            $table->decimal('valor', 15, 2)->default(0);
            $table->date('previsao_fechamento')->nullable();
            $table->date('fechada_em')->nullable();
            $table->enum('resultado', ['aberta', 'ganha', 'perdida'])->default('aberta');
            $table->string('motivo_perda', 200)->nullable();
            $table->text('observacao')->nullable();
            $table->json('tags')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['empresa_id', 'etapa_id', 'resultado']);
        });

        Schema::create('atividades_crm', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oportunidade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('tipo', ['ligacao', 'email', 'reuniao', 'tarefa', 'nota', 'whatsapp']);
            $table->string('titulo', 200);
            $table->text('descricao')->nullable();
            $table->timestamp('data_hora')->nullable();
            $table->boolean('concluida')->default(false);
            $table->timestamp('concluida_em')->nullable();
            $table->timestamps();
            $table->index(['oportunidade_id', 'concluida']);
        });

        // ─── Pedidos / Orçamentos ─────────────────────────────────────────
        Schema::create('pedidos_venda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->string('numero', 20)->nullable();
            $table->enum('tipo', ['orcamento', 'pedido'])->default('pedido');
            $table->foreignId('cliente_id')->constrained('pessoas')->restrictOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('oportunidade_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('forma_pagamento_id')->nullable()->constrained('formas_pagamento')->nullOnDelete();
            $table->foreignId('tabela_preco_id')->nullable()->constrained('tabelas_preco')->nullOnDelete();

            $table->date('data_pedido');
            $table->date('data_validade')->nullable();     // para orçamentos
            $table->date('data_entrega_prevista')->nullable();
            $table->date('data_entrega_real')->nullable();

            $table->enum('status', [
                'rascunho', 'aguardando_aprovacao', 'aprovado',
                'em_separacao', 'faturado', 'entregue',
                'cancelado', 'rejeitado',
            ])->default('rascunho');

            // Totais
            $table->decimal('total_produtos', 15, 2)->default(0);
            $table->decimal('total_desconto', 15, 2)->default(0);
            $table->decimal('total_frete', 15, 2)->default(0);
            $table->decimal('total_outras', 15, 2)->default(0);
            $table->decimal('total_pedido', 15, 2)->default(0);

            // Endereço de entrega
            $table->string('entrega_logradouro', 150)->nullable();
            $table->string('entrega_numero', 20)->nullable();
            $table->string('entrega_bairro', 100)->nullable();
            $table->string('entrega_municipio', 100)->nullable();
            $table->string('entrega_uf', 2)->nullable();
            $table->string('entrega_cep', 10)->nullable();

            $table->text('observacao')->nullable();
            $table->text('observacao_interna')->nullable();
            $table->string('condicao_pagamento', 100)->nullable();
            $table->foreignId('nfe_id')->nullable()->constrained('nfes')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'status', 'tipo']);
            $table->index(['cliente_id', 'status']);
            $table->index('data_pedido');
        });

        Schema::create('pedido_venda_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_venda_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained()->restrictOnDelete();
            $table->smallInteger('numero_item');
            $table->string('descricao', 150);
            $table->decimal('quantidade', 15, 4);
            $table->decimal('preco_unitario', 15, 4);
            $table->decimal('desconto_percentual', 6, 4)->default(0);
            $table->decimal('desconto_valor', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->decimal('custo_unitario', 15, 4)->default(0);  // snapshot do custo
            $table->decimal('margem', 6, 4)->default(0);           // calculado
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->index(['pedido_venda_id', 'numero_item']);
        });

        // ─── Comissões ────────────────────────────────────────────────────
        Schema::create('regras_comissao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->string('nome', 100);
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('percentual', 6, 4);
            $table->enum('base_calculo', ['valor_venda', 'valor_lucro'])->default('valor_venda');
            $table->decimal('meta_minima', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('comissoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_venda_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendedor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('regra_id')->nullable()->constrained('regras_comissao')->nullOnDelete();
            $table->decimal('valor_base', 15, 2);
            $table->decimal('percentual', 6, 4);
            $table->decimal('valor_comissao', 15, 2);
            $table->enum('status', ['pendente', 'aprovada', 'paga', 'cancelada'])->default('pendente');
            $table->date('paga_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comissoes');
        Schema::dropIfExists('regras_comissao');
        Schema::dropIfExists('pedido_venda_itens');
        Schema::dropIfExists('pedidos_venda');
        Schema::dropIfExists('atividades_crm');
        Schema::dropIfExists('oportunidades');
        Schema::dropIfExists('etapas_funil');
        Schema::dropIfExists('funis_venda');
    }
};
