<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @group Onboarding
 * Retorna o progresso dos primeiros passos, derivado de dados reais do tenant.
 */
class OnboardingStatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $empresa = DB::table('empresas')->where('is_matriz', true)->first();

        return response()->json([
            'empresa_configurada' => $empresa
                && ! empty($empresa->cnpj)
                && ! empty($empresa->ie)
                && ! empty($empresa->codigo_municipio),
            'certificado_enviado' => $empresa && ! empty($empresa->certificado_path),
            'tem_produtos'        => DB::table('produtos')->exists(),
            'tem_clientes'        => DB::table('pessoas')->where('is_cliente', true)->exists(),
            'tem_nota'            => DB::table('nfes')->exists()
                || DB::table('ctes')->exists()
                || DB::table('nfses')->exists(),
        ]);
    }
}
