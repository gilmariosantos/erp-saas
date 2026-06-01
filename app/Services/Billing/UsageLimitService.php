<?php

namespace App\Services\Billing;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Controla e impõe os limites de uso de cada plano.
 *
 * Antes de emitir um documento fiscal ou criar usuário, o sistema consulta
 * este serviço para verificar se o tenant não excedeu a cota do plano.
 */
class UsageLimitService
{
    /**
     * Verifica se o tenant pode emitir mais um documento do tipo informado.
     *
     * @param string $tipo nfe|nfce|cte|nfse
     * @throws \App\Exceptions\LimiteExcedidoException
     */
    public function verificarLimite(Tenant $tenant, string $tipo): bool
    {
        $subscription = $tenant->subscription;
        if (! $subscription) {
            throw new \RuntimeException('Tenant sem assinatura.');
        }

        // Durante o trial, libera tudo
        if ($subscription->status === 'trial') {
            return true;
        }

        $plano = DB::table('plans')->where('id', $subscription->plan_id)->first();
        $uso = $this->usoAtual($tenant);

        $mapa = [
            'nfe'  => ['campo' => 'nfe_emitidas',  'limite' => 'max_nfe_mes'],
            'nfce' => ['campo' => 'nfce_emitidas', 'limite' => 'max_nfe_mes'],
            'cte'  => ['campo' => 'cte_emitidos',  'limite' => 'max_cte_mes'],
            'nfse' => ['campo' => 'nfse_emitidas', 'limite' => 'max_nfe_mes'],
        ];

        if (! isset($mapa[$tipo])) {
            return true; // tipo não controlado
        }

        $usado  = $uso->{$mapa[$tipo]['campo']} ?? 0;
        $limite = $plano->{$mapa[$tipo]['limite']} ?? 0;

        // 9999 = ilimitado (Enterprise)
        if ($limite >= 9999) {
            return true;
        }

        if ($usado >= $limite) {
            throw new \App\Exceptions\LimiteExcedidoException(
                "Limite do plano atingido: {$usado}/{$limite} {$tipo} este mês. "
                . "Faça upgrade para emitir mais."
            );
        }

        return true;
    }

    /**
     * Incrementa o contador de uso após emissão bem-sucedida.
     */
    public function registrarUso(Tenant $tenant, string $tipo): void
    {
        $campo = match ($tipo) {
            'nfe'  => 'nfe_emitidas',
            'nfce' => 'nfce_emitidas',
            'cte'  => 'cte_emitidos',
            'nfse' => 'nfse_emitidas',
            default => null,
        };

        if (! $campo) return;

        $competencia = now()->format('Y-m');

        DB::table('usage_counters')->updateOrInsert(
            ['tenant_id' => $tenant->id, 'competencia' => $competencia],
            ['updated_at' => now()]
        );

        DB::table('usage_counters')
            ->where('tenant_id', $tenant->id)
            ->where('competencia', $competencia)
            ->increment($campo);
    }

    /**
     * Retorna o uso do mês corrente.
     */
    public function usoAtual(Tenant $tenant): object
    {
        $competencia = now()->format('Y-m');

        $uso = DB::table('usage_counters')
            ->where('tenant_id', $tenant->id)
            ->where('competencia', $competencia)
            ->first();

        return $uso ?? (object) [
            'nfe_emitidas'  => 0,
            'nfce_emitidas' => 0,
            'cte_emitidos'  => 0,
            'nfse_emitidas' => 0,
            'usuarios_ativos' => 0,
            'storage_usado_gb' => 0,
        ];
    }

    /**
     * Retorna um resumo de uso vs limites para exibir no painel do cliente.
     */
    public function resumoUso(Tenant $tenant): array
    {
        $subscription = $tenant->subscription;
        $plano = DB::table('plans')->where('id', $subscription->plan_id ?? 0)->first();
        $uso = $this->usoAtual($tenant);

        if (! $plano) return [];

        return [
            'nfe' => [
                'usado'  => $uso->nfe_emitidas,
                'limite' => $plano->max_nfe_mes,
                'percentual' => $plano->max_nfe_mes > 0
                    ? round(($uso->nfe_emitidas / $plano->max_nfe_mes) * 100, 1) : 0,
            ],
            'cte' => [
                'usado'  => $uso->cte_emitidos,
                'limite' => $plano->max_cte_mes,
                'percentual' => $plano->max_cte_mes > 0
                    ? round(($uso->cte_emitidos / $plano->max_cte_mes) * 100, 1) : 0,
            ],
            'plano' => $plano->name,
            'status' => $subscription->status,
        ];
    }
}
