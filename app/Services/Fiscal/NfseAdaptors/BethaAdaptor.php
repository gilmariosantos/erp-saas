<?php
namespace App\Services\Fiscal\NfseAdaptors;
use App\Models\Nfse;

class BethaAdaptor implements NfseAdaptorInterface
{
    public function emitir(Nfse $nfse): array
    {
        // Implementação específica do padrão BethaAdaptor
        // Usa a biblioteca nfephp-org/sped-nfse
        throw new \RuntimeException('Adaptador BethaAdaptor requer configuração do certificado e endpoint municipal.');
    }

    public function cancelar(Nfse $nfse, string $motivo): array
    {
        throw new \RuntimeException('Cancelamento BethaAdaptor requer configuração municipal.');
    }

    public function consultar(Nfse $nfse): array
    {
        return ['status' => $nfse->status, 'numero' => $nfse->numero];
    }
}
