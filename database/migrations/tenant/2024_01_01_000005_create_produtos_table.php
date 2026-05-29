<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas de produtos, serviços, unidades de medida e grupos.
 * Inclui todos os campos fiscais necessários para emissão de NF-e.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_medida', function (Blueprint $table) {
            $table->id();
            $table->string('sigla', 10)->unique();               // UN, KG, CX, L...
            $table->string('descricao', 60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('categorias_produto', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->foreignId('parent_id')->nullable()
                  ->constrained('categorias_produto')->nullOnDelete();
            $table->integer('nivel')->default(1);
            $table->string('cor', 7)->nullable();                // hex color
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60)->nullable();
            $table->string('codigo_barras', 60)->nullable();
            $table->string('codigo_barras_tributavel', 60)->nullable();
            $table->string('descricao', 150);
            $table->text('descricao_complementar')->nullable();
            $table->enum('tipo', ['P', 'S', 'C'])
                  ->default('P')
                  ->comment('P=Produto, S=Serviço, C=Combo');

            $table->foreignId('unidade_medida_id')->nullable()
                  ->constrained('unidades_medida')->nullOnDelete();
            $table->foreignId('unidade_medida_trib_id')->nullable()
                  ->constrained('unidades_medida')->nullOnDelete();
            $table->foreignId('categoria_id')->nullable()
                  ->constrained('categorias_produto')->nullOnDelete();

            // Preços
            $table->decimal('preco_custo', 15, 4)->default(0);
            $table->decimal('preco_venda', 15, 4)->default(0);
            $table->decimal('preco_minimo', 15, 4)->default(0);
            $table->decimal('margem_lucro', 7, 4)->default(0);

            // Estoque
            $table->boolean('controla_estoque')->default(true);
            $table->decimal('estoque_atual', 15, 4)->default(0);
            $table->decimal('estoque_minimo', 15, 4)->default(0);
            $table->decimal('estoque_maximo', 15, 4)->default(0);
            $table->decimal('estoque_reservado', 15, 4)->default(0);
            $table->string('localizacao', 50)->nullable();       // prateleira/corredor

            // Fiscal — NCM, CFOP, CST, CEST...
            $table->string('ncm', 10)->nullable();               // código NCM
            $table->string('cest', 9)->nullable();               // CEST
            $table->string('cfop', 5)->nullable();               // CFOP padrão saída
            $table->string('origem', 1)->default('0');           // 0=Nacional, 1=Imp. direto...

            // ICMS
            $table->string('cst_icms', 3)->nullable();
            $table->string('csosn', 4)->nullable();              // para Simples Nacional
            $table->decimal('aliquota_icms', 6, 4)->default(0);
            $table->decimal('reducao_bc_icms', 6, 4)->default(0);

            // IPI
            $table->string('cst_ipi', 2)->nullable();
            $table->string('codigo_enquadramento_ipi', 5)->nullable();
            $table->decimal('aliquota_ipi', 6, 4)->default(0);

            // PIS
            $table->string('cst_pis', 2)->nullable();
            $table->decimal('aliquota_pis', 6, 4)->default(0);

            // COFINS
            $table->string('cst_cofins', 2)->nullable();
            $table->decimal('aliquota_cofins', 6, 4)->default(0);

            // Serviço (NFS-e)
            $table->string('codigo_servico', 20)->nullable();    // LC 116/03
            $table->decimal('aliquota_iss', 5, 4)->default(0);
            $table->boolean('retencao_iss')->default(false);
            $table->boolean('retencao_ir')->default(false);
            $table->boolean('retencao_csll')->default(false);
            $table->boolean('retencao_inss')->default(false);
            $table->boolean('retencao_pis_cofins')->default(false);

            // Dimensões e peso (para frete)
            $table->decimal('peso_bruto', 10, 4)->nullable();
            $table->decimal('peso_liquido', 10, 4)->nullable();
            $table->decimal('comprimento', 8, 2)->nullable();
            $table->decimal('largura', 8, 2)->nullable();
            $table->decimal('altura', 8, 2)->nullable();
            $table->string('marca', 80)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->string('fabricante', 80)->nullable();

            $table->string('foto')->nullable();
            $table->json('fotos')->nullable();
            $table->json('atributos')->nullable();               // cor, tamanho, etc

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('codigo');
            $table->index('codigo_barras');
            $table->index('ncm');
            $table->index(['tipo', 'is_active']);
        });

        Schema::create('tabelas_preco', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->decimal('desconto_padrao', 6, 4)->default(0);
            $table->decimal('acrescimo_padrao', 6, 4)->default(0);
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fim')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tabela_preco_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tabela_preco_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained()->cascadeOnDelete();
            $table->decimal('preco', 15, 4);
            $table->decimal('desconto_max', 6, 4)->default(0);
            $table->timestamps();

            $table->unique(['tabela_preco_id', 'produto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabela_preco_itens');
        Schema::dropIfExists('tabelas_preco');
        Schema::dropIfExists('produtos');
        Schema::dropIfExists('categorias_produto');
        Schema::dropIfExists('unidades_medida');
    }
};
