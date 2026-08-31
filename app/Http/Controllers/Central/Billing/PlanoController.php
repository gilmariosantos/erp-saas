<?php
namespace App\Http\Controllers\Central\Billing;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

/**
 * @group Planos (Público)
 * Lista os planos disponíveis para a página de pricing.
 * Endpoint público — não exige autenticação.
 */
class PlanoController extends Controller
{
    public function index(): JsonResponse
    {
        $planos = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($p) => [
                'id'            => $p->id,
                'nome'          => $p->name,
                'slug'          => $p->slug,
                'descricao'     => $p->description,
                'preco_mensal'  => (float) $p->price_monthly,
                'preco_anual'   => (float) $p->price_annual,
                'economia_anual'=> $this->economiaAnual($p),
                'limites'       => [
                    'usuarios'  => $p->max_users,
                    'empresas'  => $p->max_empresas,
                    'nfe_mes'   => $p->max_nfe_mes,
                    'cte_mes'   => $p->max_cte_mes,
                    'storage_gb'=> $p->storage_gb,
                ],
                'features'      => $p->features ?? [],
                'destaque'      => $p->slug === 'pro', // plano recomendado
            ]);

        return response()->json(['data' => $planos]);
    }

    /**
     * Calcula a economia percentual ao optar pelo plano anual.
     */
    private function economiaAnual(Plan $p): int
    {
        if ($p->price_monthly <= 0) return 0;
        $totalMensal = $p->price_monthly * 12;
        if ($totalMensal <= 0) return 0;
        return (int) round((($totalMensal - $p->price_annual) / $totalMensal) * 100);
    }
}
