<?php

namespace App\Services\Fiscal\Contracts;

use App\Models\Empresa;

/**
 * Contrato para serviços de emissão de documentos fiscais eletrônicos.
 */
interface FiscalDocumentInterface
{
    public function emitir(mixed $documento): mixed;
    public function cancelar(mixed $documento, string $justificativa): mixed;
    public function consultar(mixed $documento): array;
}
