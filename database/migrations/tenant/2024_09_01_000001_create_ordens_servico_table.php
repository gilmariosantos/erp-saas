<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de Ordem de Serviço (OS).
 *
 * Configurável para qualquer ramo (assistência técnica, oficina, prestação
 * de serviços) através de:
 *  - tipos_os: define os "tipos" de OS que cada empresa usa
 *  - status_os: fluxo de status customizável por empresa (kanban)
 *  - campos de equipamento opcionais (preenchidos conforme o ramo)
 *
 * Integra com: estoque (baixa de peças), fiscal (NFS-e) e financeiro (a receber).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── Tipos de OS (configurável por empresa) ───────────────────────
        Schema::create('tipos_os', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->string('nome', 80);                  // ex: "Conserto", "Revisão", "Instalação"
            $table->string('prefixo', 10)->nullable();   // ex: "OS", "REV"
            $table->boolean('exige_equipamento')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Status de OS (fluxo kanban configurável) ─────────────────────
        Schema::create('status_os', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->string('nome', 60);                  // ex: "Aberta", "Em análise", "Aguardando peça"
            $table->string('cor', 7)->default('#3B82F6');
            $table->integer('ordem')->default(0);
            $table->boolean('is_inicial')->default(false);
            $table->boolean('is_final')->default(false); // status que "fecha" a OS
            $table->boolean('is_cancelado')->default(false);
            $table->timestamps();
        });

        // ─── Equipamentos do cliente ──────────────────────────────────────
        Schema::create('equipamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->foreignId('cliente_id')->constrained('pessoas')->restrictOnDelete();
            $table->string('tipo', 60)->nullable();      // ex: "Notebook", "Veículo", "Ar-condicionado"
            $table->string('marca', 60)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->string('numero_serie', 80)->nullable();
            $table->string('identificador', 80)->nullable(); // placa, IMEI, patrimônio...
            $table->json('atributos')->nullable();       // campos extras conforme o ramo
            $table->text('observacao')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['empresa_id', 'cliente_id']);
        });

        // ─── Ordens de Serviço ────────────────────────────────────────────
        Schema::create('ordens_servico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->string('numero', 20);
            $table->foreignId('tipo_os_id')->nullable()->constrained('tipos_os')->nullOnDelete();
            $table->foreignId('cliente_id')->constrained('pessoas')->restrictOnDelete();
            $table->foreignId('equipamento_id')->nullable()->constrained('equipamentos')->nullOnDelete();
            $table->foreignId('status_id')->nullable()->constrained('status_os')->nullOnDelete();
            $table->foreignId('tecnico_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();

            // Datas do fluxo
            $table->date('data_abertura');
            $table->date('data_previsao')->nullable();
            $table->date('data_conclusao')->nullable();
            $table->dateTime('data_aprovacao_cliente')->nullable();

            // Conteúdo da OS
            $table->text('descricao_problema')->nullable();   // relato do cliente
            $table->text('diagnostico')->nullable();          // laudo técnico
            $table->text('solucao')->nullable();              // o que foi feito
            $table->text('observacoes_internas')->nullable();

            // Valores (calculados a partir dos itens)
            $table->decimal('total_servicos', 15, 2)->default(0);
            $table->decimal('total_pecas', 15, 2)->default(0);
            $table->decimal('total_desconto', 15, 2)->default(0);
            $table->decimal('total_geral', 15, 2)->default(0);

            // Garantia
            $table->integer('garantia_dias')->default(0);
            $table->date('garantia_ate')->nullable();

            // Integrações (preenchidas ao finalizar)
            $table->foreignId('nfse_id')->nullable()->constrained('nfses')->nullOnDelete();
            $table->foreignId('lancamento_id')->nullable()->constrained('lancamentos')->nullOnDelete();
            $table->boolean('estoque_baixado')->default(false);

            // Aprovação do orçamento
            $table->enum('situacao', [
                'orcamento', 'aprovada', 'em_execucao',
                'concluida', 'entregue', 'cancelada',
            ])->default('orcamento');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'situacao']);
            $table->index(['cliente_id', 'situacao']);
            $table->unique(['empresa_id', 'numero']);
        });

        // ─── Itens da OS (serviços e peças) ───────────────────────────────
        Schema::create('ordem_servico_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordem_servico_id')->constrained()->cascadeOnDelete();
            $table->enum('tipo', ['servico', 'peca']);
            $table->foreignId('produto_id')->nullable()->constrained()->nullOnDelete();
            $table->string('descricao', 200);
            $table->decimal('quantidade', 15, 4)->default(1);
            $table->decimal('valor_unitario', 15, 4)->default(0);
            $table->decimal('desconto_valor', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->foreignId('tecnico_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('aprovado')->default(true);   // cliente pode recusar itens
            $table->timestamps();
            $table->index('ordem_servico_id');
        });

        // ─── Histórico de mudanças de status (timeline) ───────────────────
        Schema::create('ordem_servico_historico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordem_servico_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('status_id')->nullable()->constrained('status_os')->nullOnDelete();
            $table->string('acao', 80);                   // ex: "Status alterado", "Item adicionado"
            $table->text('descricao')->nullable();
            $table->timestamps();
            $table->index('ordem_servico_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordem_servico_historico');
        Schema::dropIfExists('ordem_servico_itens');
        Schema::dropIfExists('ordens_servico');
        Schema::dropIfExists('equipamentos');
        Schema::dropIfExists('status_os');
        Schema::dropIfExists('tipos_os');
    }
};
