<?php

namespace App\Services\Fiscal;

use App\Models\Nfe;
use NFePHP\NFe\Make;

/**
 * Monta o XML da NF-e a partir dos dados do banco.
 * Usa a biblioteca nfephp-org/sped-nfe.
 */
class NfeXmlBuilder
{
    public function build(Nfe $nfe): string
    {
        $nfe->load(['empresa', 'itens', 'cobrancas', 'volumes', 'destinatario']);

        $make = new Make();

        $this->addIdentificacao($make, $nfe);
        $this->addEmitente($make, $nfe);
        $this->addDestinatario($make, $nfe);
        $this->addItens($make, $nfe);
        $this->addTotais($make, $nfe);
        $this->addTransporte($make, $nfe);
        $this->addCobranca($make, $nfe);
        $this->addInformacoesAdicionais($make, $nfe);

        return $make->getXML();
    }

    private function addIdentificacao(Make $make, Nfe $nfe): void
    {
        $std = new \stdClass();
        $std->versao      = '4.00';
        $std->Id          = null; // gerado automaticamente
        $std->cUF         = $this->codigoUF($nfe->emitente_uf ?? 'SP');
        $std->cNF         = str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        $std->natOp       = $nfe->natureza_operacao ?? 'VENDA';
        $std->mod         = (int) $nfe->modelo;
        $std->serie       = (int) $nfe->serie;
        $std->nNF         = (int) $nfe->numero;
        $std->dhEmi       = $nfe->data_emissao?->format('Y-m-d\TH:i:sP') ?? now()->format('Y-m-d\TH:i:sP');
        $std->dhSaiEnt    = $nfe->data_saida_entrada?->format('Y-m-d\TH:i:sP');
        $std->tpNF        = (int) $nfe->operacao;
        $std->idDest      = 1;
        $std->cMunFG      = $nfe->empresa->codigo_municipio ?? '3550308';
        $std->tpImp       = 1;
        $std->tpEmis      = (int) $nfe->tipo_emissao;
        $std->tpAmb       = $nfe->ambiente;
        $std->finNFe      = (int) $nfe->finalidade;
        $std->indFinal    = 0;
        $std->indPres     = 0;
        $std->procEmi     = 0;
        $std->verProc     = '1.0';

        $make->tagide($std);
    }

    private function addEmitente(Make $make, Nfe $nfe): void
    {
        $empresa = $nfe->empresa;
        $std = new \stdClass();
        $std->xNome     = $empresa->razao_social;
        $std->xFant     = $empresa->nome_fantasia;
        $std->IE        = preg_replace('/\D/', '', $empresa->ie ?? '');
        $std->CRT       = (int) $empresa->regime_tributario;
        $make->tagemit($std);

        $endStd = new \stdClass();
        $endStd->xLgr   = $empresa->logradouro;
        $endStd->nro    = $empresa->numero;
        $endStd->xBairro= $empresa->bairro;
        $endStd->cMun   = $empresa->codigo_municipio;
        $endStd->xMun   = $empresa->municipio;
        $endStd->UF     = $empresa->uf;
        $endStd->CEP    = preg_replace('/\D/', '', $empresa->cep ?? '');
        $endStd->cPais  = $empresa->codigo_pais ?? '1058';
        $endStd->xPais  = $empresa->pais ?? 'Brasil';
        $endStd->fone   = preg_replace('/\D/', '', $empresa->telefone ?? '');
        $make->tagenderEmit($endStd);

        $cnpjStd = new \stdClass();
        $cnpjStd->CNPJ = preg_replace('/\D/', '', $empresa->cnpj ?? '');
        $make->tagCNPJ($cnpjStd, 'emit');
    }

    private function addDestinatario(Make $make, Nfe $nfe): void
    {
        $std = new \stdClass();
        $std->xNome   = $nfe->destinatario_nome;
        $std->email   = $nfe->destinatario_email;
        $std->indIEDest = $nfe->destinatario_indicador_ie;
        $std->IE      = preg_replace('/\D/', '', $nfe->destinatario_ie ?? '');
        $make->tagdest($std);

        $doc = preg_replace('/\D/', '', $nfe->destinatario_cnpj_cpf ?? '');
        if (strlen($doc) === 14) {
            $cnpjStd = new \stdClass();
            $cnpjStd->CNPJ = $doc;
            $make->tagCNPJ($cnpjStd, 'dest');
        } else {
            $cpfStd = new \stdClass();
            $cpfStd->CPF = $doc;
            $make->tagCPF($cpfStd, 'dest');
        }

        if ($nfe->destinatario_endereco) {
            $end = (object) $nfe->destinatario_endereco;
            $endStd = new \stdClass();
            $endStd->xLgr    = $end->logradouro ?? '';
            $endStd->nro     = $end->numero ?? 'SN';
            $endStd->xBairro = $end->bairro ?? '';
            $endStd->cMun    = $end->codigo_municipio ?? '';
            $endStd->xMun    = $end->municipio ?? '';
            $endStd->UF      = $nfe->destinatario_uf ?? 'SP';
            $endStd->CEP     = preg_replace('/\D/', '', $end->cep ?? '');
            $endStd->cPais   = '1058';
            $endStd->xPais   = 'Brasil';
            $make->tagenderDest($endStd);
        }
    }

    private function addItens(Make $make, Nfe $nfe): void
    {
        foreach ($nfe->itens as $item) {
            $prodStd = new \stdClass();
            $prodStd->item           = $item->numero_item;
            $prodStd->cProd          = $item->codigo_produto;
            $prodStd->cEAN           = $item->codigo_barras ?? 'SEM GTIN';
            $prodStd->xProd          = $item->descricao;
            $prodStd->NCM            = preg_replace('/\D/', '', $item->ncm ?? '');
            $prodStd->CFOP           = $item->cfop;
            $prodStd->uCom           = $item->unidade;
            $prodStd->qCom           = $item->quantidade;
            $prodStd->vUnCom         = $item->valor_unitario;
            $prodStd->vProd          = $item->valor_bruto;
            $prodStd->cEANTrib       = $item->codigo_barras ?? 'SEM GTIN';
            $prodStd->uTrib          = $item->unidade;
            $prodStd->qTrib          = $item->quantidade;
            $prodStd->vUnTrib        = $item->valor_unitario;
            $prodStd->vDesc          = $item->desconto ?: null;
            $prodStd->vFrete         = $item->frete ?: null;
            $prodStd->vSeg           = $item->seguro ?: null;
            $prodStd->vOutro         = $item->outras_despesas ?: null;
            $prodStd->indTot         = $item->compoe_total ? 1 : 0;
            $prodStd->origCalcTrib   = null;
            $make->tagprod($prodStd);

            // ICMS (simplificado — CRT 1 = Simples Nacional)
            $icmsStd = new \stdClass();
            $icmsStd->item  = $item->numero_item;
            $icmsStd->orig  = $item->origem;
            $icmsStd->CSOSN = $item->csosn ?? '102';
            $make->tagICMSSN($icmsStd);

            // PIS
            $pisStd = new \stdClass();
            $pisStd->item   = $item->numero_item;
            $pisStd->CST    = $item->cst_pis ?? '07';
            $make->tagPISST($pisStd);

            // COFINS
            $cofinsStd = new \stdClass();
            $cofinsStd->item = $item->numero_item;
            $cofinsStd->CST  = $item->cst_cofins ?? '07';
            $make->tagCOFINSST($cofinsStd);
        }
    }

    private function addTotais(Make $make, Nfe $nfe): void
    {
        $std = new \stdClass();
        $std->vBC       = 0;
        $std->vICMS     = $nfe->total_icms;
        $std->vICMSDeson= 0;
        $std->vFCP      = 0;
        $std->vBCST     = 0;
        $std->vST       = $nfe->total_icms_st;
        $std->vFCPST    = 0;
        $std->vFCPSTRet = 0;
        $std->vProd     = $nfe->total_produtos;
        $std->vFrete    = $nfe->total_frete;
        $std->vSeg      = $nfe->total_seguro;
        $std->vDesc     = $nfe->total_desconto;
        $std->vII       = 0;
        $std->vIPI      = $nfe->total_ipi;
        $std->vIPIDevol = 0;
        $std->vPIS      = $nfe->total_pis;
        $std->vCOFINS   = $nfe->total_cofins;
        $std->vOutro    = $nfe->total_outras;
        $std->vNF       = $nfe->total_nota;
        $std->vTotTrib  = 0;
        $make->tagICMSTot($std);
    }

    private function addTransporte(Make $make, Nfe $nfe): void
    {
        $std = new \stdClass();
        $std->modFrete = (int) $nfe->modalidade_frete;
        $make->tagtransp($std);
    }

    private function addCobranca(Make $make, Nfe $nfe): void
    {
        if ($nfe->cobrancas->isEmpty()) return;

        $fatStd = new \stdClass();
        $fatStd->nFat  = $nfe->numero;
        $fatStd->vOrig = $nfe->total_nota;
        $fatStd->vDesc = 0;
        $fatStd->vLiq  = $nfe->total_nota;
        $make->tagfat($fatStd);

        foreach ($nfe->cobrancas as $dup) {
            $dupStd = new \stdClass();
            $dupStd->nDup  = $dup->numero_duplicata;
            $dupStd->dVenc = $dup->vencimento->format('Y-m-d');
            $dupStd->vDup  = $dup->valor;
            $make->tagdup($dupStd);
        }
    }

    private function addInformacoesAdicionais(Make $make, Nfe $nfe): void
    {
        if (! $nfe->informacoes_complementares && ! $nfe->informacoes_fisco) return;
        $std = new \stdClass();
        $std->infCpl  = $nfe->informacoes_complementares;
        $std->infAdFisco = $nfe->informacoes_fisco;
        $make->taginfAdic($std);
    }

    private function codigoUF(string $uf): int
    {
        $ufs = [
            'AC'=>12,'AL'=>27,'AM'=>13,'AP'=>16,'BA'=>29,'CE'=>23,
            'DF'=>53,'ES'=>32,'GO'=>52,'MA'=>21,'MG'=>31,'MS'=>50,
            'MT'=>51,'PA'=>15,'PB'=>25,'PE'=>26,'PI'=>22,'PR'=>41,
            'RJ'=>33,'RN'=>24,'RO'=>11,'RR'=>14,'RS'=>43,'SC'=>42,
            'SE'=>28,'SP'=>35,'TO'=>17,
        ];
        return $ufs[$uf] ?? 35;
    }
}
