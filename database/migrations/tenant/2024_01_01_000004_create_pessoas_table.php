<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela de pessoas — clientes, fornecedores, transportadoras, etc.
 * Modelo unificado para evitar duplicação de dados cadastrais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pessoas', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 150);                          // razão social ou nome
            $table->string('nome_fantasia', 150)->nullable();
            $table->enum('tipo_pessoa', ['PJ', 'PF'])->default('PJ');
            $table->string('cnpj', 18)->nullable();
            $table->string('cpf', 14)->nullable();
            $table->string('rg', 25)->nullable();
            $table->string('ie', 30)->nullable();                 // Insc. Estadual
            $table->string('ie_suframa', 20)->nullable();
            $table->string('im', 30)->nullable();                 // Insc. Municipal
            $table->enum('indicador_ie', ['1', '2', '9'])->default('9');
            // 1=Contrib. ICMS | 2=Isento | 9=Não contribuinte

            // Papéis (uma pessoa pode ser cliente E fornecedor)
            $table->boolean('is_cliente')->default(false);
            $table->boolean('is_fornecedor')->default(false);
            $table->boolean('is_transportadora')->default(false);
            $table->boolean('is_vendedor')->default(false);
            $table->boolean('is_funcionario')->default(false);

            // Endereço principal
            $table->string('logradouro', 150)->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 100)->nullable();
            $table->string('bairro', 100)->nullable();
            $table->string('municipio', 100)->nullable();
            $table->string('codigo_municipio', 10)->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('cep', 10)->nullable();
            $table->string('pais', 50)->default('Brasil');
            $table->string('codigo_pais', 5)->default('1058');

            // Contato
            $table->string('telefone', 20)->nullable();
            $table->string('celular', 20)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('email_nfe', 180)->nullable();         // email para receber NF
            $table->string('website', 200)->nullable();

            // Financeiro
            $table->string('banco', 10)->nullable();
            $table->string('agencia', 10)->nullable();
            $table->string('conta', 20)->nullable();
            $table->string('pix_chave', 180)->nullable();
            $table->enum('pix_tipo', ['cpf', 'cnpj', 'email', 'telefone', 'aleatoria'])->nullable();
            $table->decimal('limite_credito', 15, 2)->default(0);
            $table->integer('prazo_pagamento')->default(0);       // dias padrão

            // Categorização
            $table->string('categoria', 60)->nullable();
            $table->string('segmento', 60)->nullable();
            $table->foreignId('vendedor_id')->nullable()
                  ->constrained('pessoas')->nullOnDelete();
            $table->string('tabela_preco', 60)->nullable();

            $table->date('data_nascimento')->nullable();
            $table->string('nacionalidade', 60)->nullable();
            $table->string('observacao')->nullable();
            $table->json('contatos_adicionais')->nullable();      // array de contatos
            $table->json('enderecos_adicionais')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('cnpj');
            $table->index('cpf');
            $table->index('nome');
            $table->index(['is_cliente', 'is_active']);
            $table->index(['is_fornecedor', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoas');
    }
};
