<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contas a Pagar e Receber.
 * Modelo unificado com tipo (pagar/receber) para simplificar consultas de fluxo de caixa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lancamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->enum('tipo', ['pagar', 'receber']);

            // Identificação
            $table->string('descricao', 200);
            $table->string('numero_documento', 60)->nullable();   // NF, boleto, contrato
            $table->string('codigo_barras', 60)->nullable();      // boleto
            $table->string('linha_digitavel', 60)->nullable();

            // Datas
            $table->date('data_emissao');
            $table->date('data_vencimento');
            $table->date('data_competencia')->nullable();         // competência contábil
            $table->date('data_pagamento')->nullable();           // preenchida ao pagar

            // Valores
            $table->decimal('valor_original', 15, 2);
            $table->decimal('valor_juros', 15, 2)->default(0);
            $table->decimal('valor_multa', 15, 2)->default(0);
            $table->decimal('valor_desconto', 15, 2)->default(0);
            $table->decimal('valor_pago', 15, 2)->default(0);
            $table->decimal('valor_aberto', 15, 2)->storedAs(
                'valor_original + valor_juros + valor_multa - valor_desconto - valor_pago'
            );

            // Relacionamentos
            $table->foreignId('pessoa_id')->nullable()
                  ->constrained('pessoas')->nullOnDelete();
            $table->foreignId('conta_bancaria_id')->nullable()
                  ->constrained('contas_bancarias')->nullOnDelete();
            $table->foreignId('plano_conta_id')->nullable()
                  ->constrained('plano_contas')->nullOnDelete();
            $table->foreignId('centro_custo_id')->nullable()
                  ->constrained('centros_custo')->nullOnDelete();
            $table->foreignId('forma_pagamento_id')->nullable()
                  ->constrained('formas_pagamento')->nullOnDelete();

            // Origem (pode ter sido gerado por NF-e, pedido, etc.)
            $table->string('origem_tipo', 60)->nullable();        // 'nfe', 'pedido', 'manual'
            $table->unsignedBigInteger('origem_id')->nullable();

            // Parcelamento
            $table->string('grupo_parcelas', 36)->nullable();     // UUID do grupo
            $table->smallInteger('parcela_numero')->default(1);
            $table->smallInteger('parcela_total')->default(1);

            // Status
            $table->enum('status', [
                'aberto',
                'parcial',
                'pago',
                'vencido',
                'cancelado',
                'renegociado',
            ])->default('aberto');

            $table->text('observacao')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_recorrente')->default(false);
            $table->string('recorrencia_tipo', 20)->nullable();   // mensal, quinzenal, semanal
            $table->integer('recorrencia_qtd')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'tipo', 'status']);
            $table->index(['empresa_id', 'data_vencimento']);
            $table->index(['pessoa_id', 'status']);
            $table->index('grupo_parcelas');
            $table->index(['origem_tipo', 'origem_id']);
        });

        // ─── Baixas / Pagamentos parciais ─────────────────────────────────
        Schema::create('lancamento_baixas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lancamento_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conta_bancaria_id')->constrained()->restrictOnDelete();
            $table->foreignId('forma_pagamento_id')->nullable()
                  ->constrained('formas_pagamento')->nullOnDelete();
            $table->date('data_pagamento');
            $table->decimal('valor_pago', 15, 2);
            $table->decimal('valor_juros', 15, 2)->default(0);
            $table->decimal('valor_multa', 15, 2)->default(0);
            $table->decimal('valor_desconto', 15, 2)->default(0);
            $table->text('observacao')->nullable();
            $table->string('comprovante')->nullable();            // path S3
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lancamento_id', 'data_pagamento']);
        });

        // ─── Transferências entre contas ──────────────────────────────────
        Schema::create('transferencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->foreignId('conta_origem_id')
                  ->constrained('contas_bancarias')->restrictOnDelete();
            $table->foreignId('conta_destino_id')
                  ->constrained('contas_bancarias')->restrictOnDelete();
            $table->date('data_transferencia');
            $table->decimal('valor', 15, 2);
            $table->string('descricao', 200)->nullable();
            $table->text('observacao')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'data_transferencia']);
        });

        // ─── Extrato (movimentações consolidadas) ─────────────────────────
        Schema::create('extrato_bancario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_bancaria_id')->constrained()->cascadeOnDelete();
            $table->date('data_movimento');
            $table->enum('tipo', ['credito', 'debito']);
            $table->decimal('valor', 15, 2);
            $table->decimal('saldo_apos', 15, 2);
            $table->string('descricao', 200)->nullable();
            $table->string('documento', 60)->nullable();
            $table->string('origem_tipo', 60)->nullable();
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->boolean('conciliado')->default(false);
            $table->timestamp('conciliado_em')->nullable();
            $table->foreignId('conciliado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['conta_bancaria_id', 'data_movimento']);
            $table->index(['conta_bancaria_id', 'conciliado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extrato_bancario');
        Schema::dropIfExists('transferencias');
        Schema::dropIfExists('lancamento_baixas');
        Schema::dropIfExists('lancamentos');
    }
};
