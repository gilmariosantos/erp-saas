<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas de CT-e (Conhecimento de Transporte Eletrônico) — leiaute 4.00
 * e CIOT (Código Identificador da Operação de Transporte) — ANTT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ctes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();

            // Identificação
            $table->string('chave_acesso', 44)->nullable()->unique();
            $table->string('numero', 9);
            $table->string('serie', 3)->default('1');
            $table->enum('modelo', ['57'])->default('57');
            $table->integer('ambiente')->default(2);
            $table->enum('tipo_emissao', ['1','3','4','5','7','9'])
                  ->default('1')
                  ->comment('1=Normal 3=Regime Especial 4=EPEC 5=Conting.FSDA 7=SVC 9=Off-line');
            $table->enum('tipo_ct', ['0','1','2','3'])
                  ->default('0')
                  ->comment('0=CTe Normal 1=CTe Complementar 2=CTe Anulação 3=CTe Substituto');
            $table->enum('tipo_servico', ['0','1','2','3','4','6'])
                  ->default('0')
                  ->comment('0=Normal 1=SubContratação 2=Redespacho 3=Red.Intermediário 4=Serv.Vinculado 6=Multimodal');

            $table->timestamp('data_emissao')->nullable();
            $table->string('natureza_operacao', 60)->nullable();
            $table->string('cfop', 5)->nullable();

            // Modal
            $table->enum('modal', ['01','02','03','04','05','06'])
                  ->default('01')
                  ->comment('01=Rodoviário 02=Aéreo 03=Aquaviário 04=Ferroviário 05=Dutoviário 06=Multimodal');

            // Emitente (copia da empresa)
            $table->string('emitente_cnpj', 18)->nullable();
            $table->string('emitente_razao_social', 150)->nullable();
            $table->string('emitente_ie', 30)->nullable();
            $table->string('emitente_uf', 2)->nullable();
            $table->string('emitente_rntrc', 20)->nullable();

            // Remetente
            $table->foreignId('remetente_id')->nullable()
                  ->constrained('pessoas')->nullOnDelete();
            $table->string('remetente_cnpj_cpf', 18)->nullable();
            $table->string('remetente_nome', 150)->nullable();
            $table->string('remetente_ie', 30)->nullable();
            $table->json('remetente_endereco')->nullable();

            // Destinatário
            $table->foreignId('destinatario_id')->nullable()
                  ->constrained('pessoas')->nullOnDelete();
            $table->string('destinatario_cnpj_cpf', 18)->nullable();
            $table->string('destinatario_nome', 150)->nullable();
            $table->string('destinatario_ie', 30)->nullable();
            $table->json('destinatario_endereco')->nullable();

            // Tomador
            $table->enum('tomador', ['0','1','2','3'])
                  ->default('0')
                  ->comment('0=Remetente 1=Expedidor 2=Recebedor 3=Destinatário');
            $table->foreignId('tomador_id')->nullable()
                  ->constrained('pessoas')->nullOnDelete();

            // Expedidor / Recebedor
            $table->foreignId('expedidor_id')->nullable()
                  ->constrained('pessoas')->nullOnDelete();
            $table->foreignId('recebedor_id')->nullable()
                  ->constrained('pessoas')->nullOnDelete();

            // Origem / Destino
            $table->string('municipio_inicio', 100)->nullable();
            $table->string('uf_inicio', 2)->nullable();
            $table->string('municipio_fim', 100)->nullable();
            $table->string('uf_fim', 2)->nullable();
            $table->string('codigo_municipio_inicio', 10)->nullable();
            $table->string('codigo_municipio_fim', 10)->nullable();

            // Totais
            $table->decimal('valor_total_servico', 15, 2)->default(0);
            $table->decimal('valor_carga', 15, 2)->default(0);
            $table->decimal('valor_receber', 15, 2)->default(0);
            $table->decimal('valor_desconto', 15, 2)->default(0);
            $table->decimal('base_calc_icms', 15, 2)->default(0);
            $table->decimal('aliquota_icms', 6, 4)->default(0);
            $table->decimal('valor_icms', 15, 2)->default(0);
            $table->decimal('percentual_reducao_bc', 6, 4)->default(0);
            $table->string('cst_icms', 3)->nullable();
            $table->string('csosn', 4)->nullable();

            // Carga
            $table->string('produto_predominante', 60)->nullable();
            $table->string('outras_caracteristicas', 30)->nullable();
            $table->decimal('valor_total_mercadoria', 15, 2)->default(0);
            $table->decimal('carga_unidade_medida', 15, 4)->nullable();
            $table->string('carga_tipo_medida', 20)->nullable();

            // Modal rodoviário
            $table->string('rntrc', 20)->nullable();
            $table->string('occ_numero', 20)->nullable();        // Ordem de Coleta de Cargas
            $table->string('occ_emitente', 20)->nullable();
            $table->date('occ_data_emissao')->nullable();

            // CIOT
            $table->string('ciot', 12)->nullable();
            $table->string('ciot_cpf_cnpj', 18)->nullable();    // CPF/CNPJ do condutor
            $table->timestamp('ciot_emitido_em')->nullable();
            $table->string('ciot_protocolo', 50)->nullable();
            $table->decimal('ciot_valor_frete', 15, 2)->nullable();
            $table->decimal('ciot_pedagio', 15, 2)->nullable();

            // Veículo
            $table->string('veiculo_placa', 10)->nullable();
            $table->string('veiculo_uf', 2)->nullable();
            $table->string('veiculo_rntrc', 20)->nullable();
            $table->json('reboques')->nullable();                 // array de reboques

            // Motorista
            $table->string('motorista_cpf', 14)->nullable();
            $table->string('motorista_nome', 150)->nullable();

            // Informações adicionais
            $table->text('informacoes_complementares')->nullable();
            $table->text('informacoes_fisco')->nullable();
            $table->json('nfes_referenciadas')->nullable();

            // XML e SEFAZ
            $table->longText('xml_enviado')->nullable();
            $table->longText('xml_retorno')->nullable();
            $table->longText('xml_cancelamento')->nullable();
            $table->string('protocolo_autorizacao', 20)->nullable();
            $table->timestamp('data_autorizacao')->nullable();

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
            $table->integer('tentativas_envio')->default(0);

            $table->string('path_xml')->nullable();
            $table->string('path_pdf')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('chave_acesso');
            $table->index(['empresa_id', 'numero', 'serie']);
            $table->index(['status', 'empresa_id']);
            $table->index('ciot');
        });

        Schema::create('cte_componentes_valor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cte_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 100);                         // Frete-valor, pedagio, etc
            $table->decimal('valor', 15, 2);
            $table->timestamps();
        });

        Schema::create('cte_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cte_id')->constrained()->cascadeOnDelete();
            $table->enum('tipo', ['NF', 'NFe', 'MDF-e', 'CT-e', 'outros'])->default('NFe');
            $table->string('chave_nfe', 44)->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('serie', 5)->nullable();
            $table->string('subserie', 5)->nullable();
            $table->date('data_emissao')->nullable();
            $table->string('cnpj_emitente', 18)->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->decimal('peso_bruto', 12, 4)->nullable();
            $table->decimal('peso_liquido', 12, 4)->nullable();
            $table->decimal('quantidade_volumes', 10, 4)->nullable();
            $table->string('unidade_volumes', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cte_documentos');
        Schema::dropIfExists('cte_componentes_valor');
        Schema::dropIfExists('ctes');
    }
};
