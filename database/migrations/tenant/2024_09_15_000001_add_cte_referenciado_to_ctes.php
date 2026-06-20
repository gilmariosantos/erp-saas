<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona o campo de chave do CT-e referenciado.
 * Usado nos tipos: Complemento de valores (1) e Substituição (3),
 * que precisam apontar para o CT-e original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ctes', function (Blueprint $table) {
            $table->string('cte_referenciado_chave', 44)->nullable()->after('tipo_servico');
        });
    }

    public function down(): void
    {
        Schema::table('ctes', function (Blueprint $table) {
            $table->dropColumn('cte_referenciado_chave');
        });
    }
};
