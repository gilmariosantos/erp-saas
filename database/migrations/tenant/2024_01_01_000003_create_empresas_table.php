<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela de empresas do tenant.
 * Um tenant pode ter múltiplas empresas (CNPJs).
 * Cada empresa tem suas configurações fiscais próprias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('razao_social', 150);
            $table->string('nome_fantasia', 150)->nullable();
            $table->string('cnpj', 18)->nullable()->unique();     // com máscara: 00.000.000/0000-00
            $table->string('cpf', 14)->nullable();                // para MEI/PF
            $table->string('ie', 30)->nullable();                 // Inscrição Estadual
            $table->string('im', 30)->nullable();                 // Inscrição Municipal
            $table->string('suframa', 20)->nullable();
            $table->enum('tipo_pessoa', ['PJ', 'PF'])->default('PJ');
            $table->string('regime_tributario', 10)->default('1');
            // 1=Simples Nacional | 2=Simples Nacional Excesso | 3=Regime Normal | 4=MEI

            // Endereço
            $table->string('logradouro', 150)->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 100)->nullable();
            $table->string('bairro', 100)->nullable();
            $table->string('municipio', 100)->nullable();
            $table->string('codigo_municipio', 10)->nullable();   // código IBGE
            $table->string('uf', 2)->nullable();
            $table->string('cep', 10)->nullable();
            $table->string('pais', 50)->default('Brasil');
            $table->string('codigo_pais', 5)->default('1058');

            // Contato
            $table->string('telefone', 20)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('website', 200)->nullable();

            // Fiscal NF-e
            $table->string('csc_id', 10)->nullable();             // CSC para NFC-e
            $table->string('csc_token')->nullable();
            $table->integer('serie_nfe')->default(1);
            $table->integer('serie_nfce')->default(1);
            $table->integer('numero_nfe')->default(0);
            $table->integer('numero_nfce')->default(0);
            $table->integer('ambiente_nfe')->default(2);          // 1=prod 2=homolog
            $table->integer('versao_nfe')->default(4);

            // Fiscal CT-e
            $table->integer('serie_cte')->default(1);
            $table->integer('numero_cte')->default(0);
            $table->integer('ambiente_cte')->default(2);
            $table->string('rntrc', 20)->nullable();              // Registro ANTT

            // Fiscal MDF-e
            $table->integer('serie_mdfe')->default(1);
            $table->integer('numero_mdfe')->default(0);
            $table->integer('ambiente_mdfe')->default(2);

            // Certificado Digital
            $table->string('certificado_path')->nullable();       // path no S3
            $table->string('certificado_senha')->nullable();      // criptografada
            $table->date('certificado_validade')->nullable();

            // NFS-e
            $table->string('cnae_principal', 10)->nullable();
            $table->string('codigo_tributacao_municipio', 20)->nullable();
            $table->decimal('aliquota_iss', 5, 4)->nullable();
            $table->string('senha_prefeitura')->nullable();       // criptografada
            $table->string('usuario_prefeitura')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_matriz')->default(true);
            $table->foreignId('empresa_matriz_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->string('logo')->nullable();
            $table->json('config')->nullable();                   // configurações extras

            $table->timestamps();
            $table->softDeletes();

            $table->index('cnpj');
            $table->index('cpf');
            $table->index('is_active');
        });

        Schema::create('empresa_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['empresa_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_users');
        Schema::dropIfExists('empresas');
    }
};
