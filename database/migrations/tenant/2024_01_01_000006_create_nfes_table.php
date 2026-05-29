<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela principal de NF-e (leiaute 4.00 SEFAZ).
 * Campos baseados no Manual de Orientação do Contribuinte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();

            // Identificação
            $table->string('chave_acesso', 44)->nullable()->unique();
            $table->string('numero', 9);
            $table->string('serie', 3)->default('1');
            $table->enum('modelo', ['55', '65'])->default('55');  // 55=NF-e 65=NFC-e
            $table->enum('tipo_emissao', ['1', '6', '7'])
                  ->default('1')
                  ->comment('1=Normal 6=Contingência SVC-AN 7=Contingência SVC-RS');
            $table->enum('finalidade', ['1', '2', '3', '4'])
                  ->default('1')
                  ->comment('1=Normal 2=Complementar 3=Ajuste 4=Devolução');
            $table->enum('operacao', ['0', '1'])
                  ->default('1')
                  ->comment('0=Entrada 1=Saída');
            $table->integer('ambiente')->default(2);               // 1=prod 2=homolog
            $table->timestamp('data_emissao')->nullable();
            $table->timestamp('data_saida_entrada')->nullable();

            // Emitente
            $table->string('emitente_cnpj', 18)->nullable();
            $table->string('emitente_ie', 30)->nullable();
            $table->string('emitente_razao_social', 150)->nullable();
            $table->string('emitente_uf', 2)->nullable();

            // Destinatário
            $table->foreignId('destinatario_id')->nullable()
                  ->constrained('pessoas')->nullOnDelete();
            $table->string('destinatario_cnpj_cpf', 18)->nullable();
            $table->string('destinatario_nome', 150)->nullable();
            $table->string('destinatario_email', 180)->nullable();
            $table->string('destinatario_ie', 30)->nullable();
            $table->string('destinatario_uf', 2)->nullable();
            $table->integer('destinatario_indicador_ie')->default(9);
            $table->json('destinatario_endereco')->nullable();

            // Totais
            $table->decimal('total_produtos', 15, 2)->default(0);
            $table->decimal('total_desconto', 15, 2)->default(0);
            $table->decimal('total_frete', 15, 2)->default(0);
            $table->decimal('total_seguro', 15, 2)->default(0);
            $table->decimal('total_outras', 15, 2)->default(0);
            $table->decimal('total_ipi', 15, 2)->default(0);
            $table->decimal('total_icms', 15, 2)->default(0);
            $table->decimal('total_icms_st', 15, 2)->default(0);
            $table->decimal('total_pis', 15, 2)->default(0);
            $table->decimal('total_cofins', 15, 2)->default(0);
            $table->decimal('total_nota', 15, 2)->default(0);

            // Transporte
            $table->enum('modalidade_frete', ['0','1','2','3','4','9'])
                  ->default('9')
                  ->comment('0=Emitente 1=Destinatário 2=Terceiros 3=Próprio Ren. 4=Próprio Dest. 9=Sem frete');
            $table->foreignId('transportadora_id')->nullable()
                  ->constrained('pessoas')->nullOnDelete();
            $table->json('transporte_veiculo')->nullable();
            $table->decimal('transporte_peso_bruto', 12, 4)->nullable();
            $table->decimal('transporte_peso_liquido', 12, 4)->nullable();
            $table->integer('transporte_quantidade_volumes')->nullable();
            $table->string('transporte_especie')->nullable();
            $table->string('transporte_marca')->nullable();
            $table->string('transporte_numeracao')->nullable();

            // Informações adicionais
            $table->text('informacoes_complementares')->nullable();
            $table->text('informacoes_fisco')->nullable();
            $table->string('natureza_operacao', 100)->nullable();
            $table->string('cfop_predominante', 5)->nullable();

            // Referências
            $table->json('nfes_referenciadas')->nullable();        // chaves de NF-e referenciadas

            // XML e processamento SEFAZ
            $table->longText('xml_enviado')->nullable();
            $table->longText('xml_retorno')->nullable();
            $table->longText('xml_cancelamento')->nullable();
            $table->longText('xml_carta_correcao')->nullable();
            $table->string('protocolo_autorizacao', 20)->nullable();
            $table->timestamp('data_autorizacao')->nullable();
            $table->string('digest_value', 100)->nullable();

            // Status
            $table->enum('status', [
                'rascunho',
                'pendente',
                'processando',
                'autorizada',
                'cancelada',
                'denegada',
                'rejeitada',
                'contingencia',
                'inutilizada',
            ])->default('rascunho');
            $table->string('motivo_rejeicao', 500)->nullable();
            $table->integer('codigo_retorno')->nullable();
            $table->string('descricao_retorno', 200)->nullable();
            $table->integer('tentativas_envio')->default(0);
            $table->timestamp('ultima_tentativa_em')->nullable();

            // Cancelamento
            $table->timestamp('cancelada_em')->nullable();
            $table->string('motivo_cancelamento', 200)->nullable();
            $table->string('protocolo_cancelamento', 20)->nullable();

            // Carta de Correção
            $table->timestamp('cce_em')->nullable();
            $table->string('cce_descricao', 1000)->nullable();
            $table->string('protocolo_cce', 20)->nullable();
            $table->integer('cce_sequencia')->default(0);

            // Caminhos S3
            $table->string('path_xml')->nullable();
            $table->string('path_pdf')->nullable();
            $table->string('path_xml_cancelamento')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('chave_acesso');
            $table->index(['empresa_id', 'numero', 'serie']);
            $table->index(['status', 'empresa_id']);
            $table->index('data_emissao');
        });

        Schema::create('nfe_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nfe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produto_id')->nullable()->constrained()->nullOnDelete();
            $table->smallInteger('numero_item');

            // Produto
            $table->string('codigo_produto', 60)->nullable();
            $table->string('codigo_barras', 60)->nullable();
            $table->string('descricao', 150);
            $table->string('ncm', 10)->nullable();
            $table->string('cest', 9)->nullable();
            $table->string('cfop', 5);
            $table->string('unidade', 10);
            $table->decimal('quantidade', 15, 4);
            $table->decimal('valor_unitario', 15, 10);
            $table->decimal('valor_bruto', 15, 2);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('frete', 15, 2)->default(0);
            $table->decimal('seguro', 15, 2)->default(0);
            $table->decimal('outras_despesas', 15, 2)->default(0);
            $table->decimal('valor_total', 15, 2);
            $table->string('origem', 1)->default('0');
            $table->boolean('compoe_total')->default(true);

            // ICMS
            $table->string('cst_icms', 3)->nullable();
            $table->string('csosn', 4)->nullable();
            $table->decimal('base_calc_icms', 15, 2)->default(0);
            $table->decimal('aliquota_icms', 6, 4)->default(0);
            $table->decimal('valor_icms', 15, 2)->default(0);
            $table->decimal('base_calc_icms_st', 15, 2)->default(0);
            $table->decimal('aliquota_icms_st', 6, 4)->default(0);
            $table->decimal('valor_icms_st', 15, 2)->default(0);

            // IPI
            $table->string('cst_ipi', 2)->nullable();
            $table->string('codigo_enquadramento_ipi', 5)->nullable();
            $table->decimal('base_calc_ipi', 15, 2)->default(0);
            $table->decimal('aliquota_ipi', 6, 4)->default(0);
            $table->decimal('valor_ipi', 15, 2)->default(0);

            // PIS
            $table->string('cst_pis', 2)->nullable();
            $table->decimal('base_calc_pis', 15, 2)->default(0);
            $table->decimal('aliquota_pis', 6, 4)->default(0);
            $table->decimal('valor_pis', 15, 2)->default(0);

            // COFINS
            $table->string('cst_cofins', 2)->nullable();
            $table->decimal('base_calc_cofins', 15, 2)->default(0);
            $table->decimal('aliquota_cofins', 6, 4)->default(0);
            $table->decimal('valor_cofins', 15, 2)->default(0);

            $table->text('informacoes_adicionais')->nullable();
            $table->timestamps();

            $table->index(['nfe_id', 'numero_item']);
        });

        Schema::create('nfe_cobrancas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nfe_id')->constrained()->cascadeOnDelete();
            $table->string('numero_duplicata', 20);
            $table->date('vencimento');
            $table->decimal('valor', 15, 2);
            $table->timestamps();
        });

        Schema::create('nfe_volumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nfe_id')->constrained()->cascadeOnDelete();
            $table->integer('quantidade')->nullable();
            $table->string('especie', 60)->nullable();
            $table->string('marca', 60)->nullable();
            $table->string('numeracao', 60)->nullable();
            $table->decimal('peso_liquido', 12, 4)->nullable();
            $table->decimal('peso_bruto', 12, 4)->nullable();
            $table->json('lacres')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_volumes');
        Schema::dropIfExists('nfe_cobrancas');
        Schema::dropIfExists('nfe_itens');
        Schema::dropIfExists('nfes');
    }
};
