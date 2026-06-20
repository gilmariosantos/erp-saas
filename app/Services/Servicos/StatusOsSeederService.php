<?php
namespace App\Services\Servicos;

use App\Models\StatusOs;
use App\Models\TipoOs;

/**
 * Cria tipos e status padrão de OS ao provisionar uma empresa.
 * O cliente pode customizar depois pela tela de configuração.
 */
class StatusOsSeederService
{
    public function criarPadroes(int $empresaId): void
    {
        $statusPadrao = [
            ['nome' => 'Aberta',          'cor' => '#3B82F6', 'ordem' => 0, 'is_inicial' => true],
            ['nome' => 'Em análise',      'cor' => '#F59E0B', 'ordem' => 1],
            ['nome' => 'Aguardando peça', 'cor' => '#EF4444', 'ordem' => 2],
            ['nome' => 'Em execução',     'cor' => '#8B5CF6', 'ordem' => 3],
            ['nome' => 'Concluída',       'cor' => '#10B981', 'ordem' => 4, 'is_final' => true],
            ['nome' => 'Cancelada',       'cor' => '#6B7280', 'ordem' => 5, 'is_cancelado' => true],
        ];

        foreach ($statusPadrao as $s) {
            StatusOs::firstOrCreate(
                ['empresa_id' => $empresaId, 'nome' => $s['nome']],
                array_merge($s, ['empresa_id' => $empresaId])
            );
        }

        // Tipo padrão
        TipoOs::firstOrCreate(
            ['empresa_id' => $empresaId, 'nome' => 'Serviço Geral'],
            ['empresa_id' => $empresaId, 'prefixo' => 'OS', 'is_active' => true]
        );
    }
}
