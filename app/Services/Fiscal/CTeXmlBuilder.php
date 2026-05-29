<?php

namespace App\Services\Fiscal;

use App\Models\Cte;
use NFePHP\CTe\Make;

/**
 * Monta o XML do CT-e a partir dos dados do banco.
 * Usa a biblioteca nfephp-org/sped-cte.
 */
class CTeXmlBuilder
{
    public function build(Cte $cte): string
    {
        $cte->load(['empresa', 'remetente', 'destinatario', 'documentos', 'componentes']);

        $make = new Make();
        // A construção do CT-e segue estrutura similar à NF-e
        // mas com tags específicas do leiaute 4.00 do CT-e.
        // Implementação completa na sprint de integração fiscal.

        return $make->getXML();
    }
}
