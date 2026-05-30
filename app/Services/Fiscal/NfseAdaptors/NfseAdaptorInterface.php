<?php
namespace App\Services\Fiscal\NfseAdaptors;
use App\Models\Nfse;

interface NfseAdaptorInterface
{
    public function emitir(Nfse $nfse): array;
    public function cancelar(Nfse $nfse, string $motivo): array;
    public function consultar(Nfse $nfse): array;
}
