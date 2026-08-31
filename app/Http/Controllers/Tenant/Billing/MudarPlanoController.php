<?php
namespace App\Http\Controllers\Tenant\Billing;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\Billing\UsageLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @group Assinatura — Mudança de plano
 * Permite ao cliente fazer upgrade ou downgrade do plano.
 */
class MudarPlanoController extends Controller
{
    public function __construct(private readonly UsageLimitService $usage) {}

    /**
     * Simula a mudança: mostra diferença de preço e valida se o downgrade
     * é possível (uso atual não pode exceder os limites do plano menor).
     */
    public function simular(Request $request): JsonResponse
    {
        $request->validate([
            'plano_id' => ['required', 'exists:plans,id'],
            'ciclo'    => ['required', 'in:mensal,anual'],
        ]);

        $tenant = tenant();
        $novoPlano = Plan::findOrFail($request->integer('plano_id'));
        $assinatura = $tenant->subscription;
        $planoAtual = Plan::find($assinatura->plan_id);

        $tipo = $novoPlano->price_monthly > ($planoAtual->price_monthly ?? 0)
            ? 'upgrade' : 'downgrade';

        // Se for downgrade, valida se o uso atual cabe nos novos limites
        $bloqueios = [];
        if ($tipo === 'downgrade') {
            $uso = $this->usage->usoAtual($tenant);
            if ($uso->nfe_emitidas > $novoPlano->max_nfe_mes) {
                $bloqueios[] = "Você já emitiu {$uso->nfe_emitidas} NF-e este mês, acima do limite de {$novoPlano->max_nfe_mes} do plano {$novoPlano->name}.";
            }
            if ($uso->cte_emitidos > $novoPlano->max_cte_mes) {
                $bloqueios[] = "Você já emitiu {$uso->cte_emitidos} CT-e este mês, acima do limite de {$novoPlano->max_cte_mes}.";
            }
        }

        $preco = $request->ciclo === 'anual' ? $novoPlano->price_annual : $novoPlano->price_monthly;

        return response()->json([
            'tipo'          => $tipo,
            'plano_atual'   => $planoAtual?->name,
            'plano_novo'    => $novoPlano->name,
            'preco'         => (float) $preco,
            'ciclo'         => $request->ciclo,
            'pode_mudar'    => empty($bloqueios),
            'bloqueios'     => $bloqueios,
        ]);
    }

    /**
     * Efetiva a mudança de plano.
     */
    public function mudar(Request $request): JsonResponse
    {
        $request->validate([
            'plano_id' => ['required', 'exists:plans,id'],
            'ciclo'    => ['required', 'in:mensal,anual'],
        ]);

        $tenant = tenant();
        $novoPlano = Plan::findOrFail($request->integer('plano_id'));

        // Revalida bloqueios de downgrade no momento da efetivação
        $simulacao = $this->simular($request)->getData(true);
        if (! $simulacao['pode_mudar']) {
            return response()->json([
                'message'   => 'Não é possível mudar de plano agora.',
                'bloqueios' => $simulacao['bloqueios'],
            ], 422);
        }

        DB::transaction(function () use ($tenant, $novoPlano, $request) {
            $tenant->subscription->update([
                'plan_id' => $novoPlano->id,
                'ciclo'   => $request->ciclo,
            ]);
        });

        Log::info('Plano alterado', [
            'tenant'     => $tenant->id,
            'novo_plano' => $novoPlano->slug,
            'ciclo'      => $request->ciclo,
        ]);

        return response()->json([
            'message' => "Plano alterado para {$novoPlano->name} com sucesso!",
            'plano'   => $novoPlano->name,
        ]);
    }
}
