<?php
namespace App\Services\Fiscal\NfseAdaptors;
use App\Models\Nfse;

class GinfesAdaptor implements NfseAdaptorInterface
{
    public function emitir(Nfse $nfse): array
    {
        // Implementação específica do padrão GinfesAdaptor
        // Usa a biblioteca nfephp-org/sped-nfse
        throw new \RuntimeException('Adaptador GinfesAdaptor requer configuração do certificado e endpoint municipal.');
    }

    public function cancelar(Nfse $nfse, string $motivo): array
    {
        throw new \RuntimeException('Cancelamento GinfesAdaptor requer configuração municipal.');
    }

    public function consultar(Nfse $nfse): array
    {
        return ['status' => $nfse->status, 'numero' => $nfse->numero];
    }
}
