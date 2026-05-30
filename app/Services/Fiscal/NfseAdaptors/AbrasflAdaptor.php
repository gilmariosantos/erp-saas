<?php
namespace App\Services\Fiscal\NfseAdaptors;
use App\Models\Nfse;

class AbrasflAdaptor implements NfseAdaptorInterface
{
    public function emitir(Nfse $nfse): array
    {
        // Implementação específica do padrão AbrasflAdaptor
        // Usa a biblioteca nfephp-org/sped-nfse
        throw new \RuntimeException('Adaptador AbrasflAdaptor requer configuração do certificado e endpoint municipal.');
    }

    public function cancelar(Nfse $nfse, string $motivo): array
    {
        throw new \RuntimeException('Cancelamento AbrasflAdaptor requer configuração municipal.');
    }

    public function consultar(Nfse $nfse): array
    {
        return ['status' => $nfse->status, 'numero' => $nfse->numero];
    }
}
