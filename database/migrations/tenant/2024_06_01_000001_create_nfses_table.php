<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->foreignId('tomador_id')->nullable()->constrained('pessoas')->nullOnDelete();

            // Identificação
            $table->string('numero', 20)->nullable();
            $table->string('numero_rps', 20)->nullable();
            $table->string('serie_rps', 5)->nullable();
            $table->string('tipo_rps', 5)->default('RPS');
            $table->integer('ambiente')->default(2);
            $table->string('padrao_municipal', 30)->default('abrasf');
            $table->string('codigo_municipio', 10)->nullable();
            $table->string('codigo_municipio_prestacao', 10)->nullable();

            // Datas
            $table->timestamp('data_emissao')->nullable();
            $table->timestamp('data_competencia')->nullable();

            // Serviço
            $table->text('descricao_servico');
            $table->string('codigo_servico', 20)->nullable();
            $table->string('codigo_tributacao_municipio', 20)->nullable();
            $table->string('cnae', 10)->nullable();
            $table->string('natureza_operacao', 5)->default('1');

            // Valores
            $table->decimal('valor_servico', 15, 2)->default(0);
            $table->decimal('valor_deducoes', 15, 2)->default(0);
            $table->decimal('valor_pis', 15, 2)->default(0);
            $table->decimal('valor_cofins', 15, 2)->default(0);
            $table->decimal('valor_inss', 15, 2)->default(0);
            $table->decimal('valor_ir', 15, 2)->default(0);
            $table->decimal('valor_csll', 15, 2)->default(0);
            $table->decimal('outras_retencoes', 15, 2)->default(0);
            $table->decimal('valor_iss', 15, 2)->default(0);
            $table->decimal('aliquota_iss', 5, 4)->default(0);
            $table->decimal('base_calculo', 15, 2)->default(0);
            $table->decimal('valor_liquido', 15, 2)->default(0);
            $table->boolean('iss_retido')->default(false);

            // Tomador
            $table->string('tomador_cnpj_cpf', 18)->nullable();
            $table->string('tomador_nome', 150)->nullable();
            $table->string('tomador_email', 180)->nullable();
            $table->string('tomador_ie', 30)->nullable();
            $table->string('tomador_im', 30)->nullable();
            $table->json('tomador_endereco')->nullable();

            // Retorno prefeitura
            $table->string('numero_verificacao', 100)->nullable();
            $table->string('codigo_verificacao', 100)->nullable();
            $table->longText('xml_enviado')->nullable();
            $table->longText('xml_retorno')->nullable();
            $table->longText('xml_cancelamento')->nullable();
            $table->string('path_xml')->nullable();
            $table->string('path_pdf')->nullable();
            $table->string('link_nfse')->nullable();

            $table->enum('status', [
                'rascunho','pendente','processando',
                'autorizada','cancelada','substituida','rejeitada',
            ])->default('rascunho');
            $table->string('motivo_rejeicao', 500)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'status']);
            $table->index('data_emissao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfses');
    }
};
