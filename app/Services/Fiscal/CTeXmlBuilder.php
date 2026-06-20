<?php

namespace App\Services\Fiscal;

use App\Models\Cte;
use NFePHP\CTe\Make;

/**
 * Monta o XML do CT-e (Conhecimento de Transporte Eletronico).
 *
 * Leiaute 4.00 - modal RODOVIARIO completo.
 * Suporta todos os tipos de CT-e:
 *   tipo_ct (tpCTe): 0=Normal, 1=Complemento de valores, 2=Anulacao, 3=Substituicao
 *   tipo_servico (tpServ): 0=Normal, 1=Subcontratacao, 2=Redespacho,
 *                          3=Redespacho intermediario, 4=Servico vinculado multimodal
 *   tomador (toma): 0=Remetente, 1=Expedidor, 2=Recebedor, 3=Destinatario, 4=Outros
 *
 * Usa a biblioteca nfephp-org/sped-cte.
 *
 * IMPORTANTE: construido conforme a documentacao da sped-cte e o MOC do CT-e.
 * A validacao final do schema XSD ocorre no servidor com a SEFAZ de homologacao.
 */
class CTeXmlBuilder
{
    private Make $make;

    public function build(Cte $cte): string
    {
        $cte->load([
            'empresa', 'remetente', 'destinatario', 'tomadorPessoa',
            'documentos', 'componentes',
        ]);

        $this->make = new Make();

        $this->addInfCte($cte);
        $this->addIdentificacao($cte);
        $this->addTomador($cte);
        $this->addEmitente($cte);
        $this->addRemetente($cte);
        $this->addDestinatario($cte);
        $this->addValores($cte);
        $this->addImpostos($cte);
        $this->addCarga($cte);
        $this->addDocumentosTransportados($cte);
        $this->addModalRodoviario($cte);
        $this->addInformacoesAdicionais($cte);
        $this->addCteReferenciado($cte);

        return $this->make->getXML();
    }

    private function addInfCte(Cte $cte): void
    {
        $std = new \stdClass();
        $std->versao = '4.00';
        $std->Id     = null;
        $this->make->taginfCte($std);
    }

    private function addIdentificacao(Cte $cte): void
    {
        $std = new \stdClass();
        $std->cUF     = $this->codigoUF($cte->emitente_uf ?? $cte->uf_inicio ?? 'SP');
        $std->cCT     = str_pad((string) rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        $std->CFOP    = $cte->cfop ?? '5353';
        $std->natOp   = $cte->natureza_operacao ?? 'PRESTACAO DE SERVICO DE TRANSPORTE';
        $std->mod     = 57;
        $std->serie   = (int) ($cte->serie ?? 1);
        $std->nCT     = (int) $cte->numero;
        $std->dhEmi   = ($cte->data_emissao ?? now())->format('Y-m-d\TH:i:sP');
        $std->tpImp   = 1;
        $std->tpEmis  = (int) ($cte->tipo_emissao ?? 1);
        $std->tpAmb   = (int) ($cte->ambiente ?? 2);
        $std->tpCTe   = (int) ($cte->tipo_ct ?? 0);
        $std->procEmi = 0;
        $std->verProc = '1.0.0';
        $std->cMunEnv = $cte->codigo_municipio_inicio ?? $cte->empresa->codigo_municipio ?? '3550308';
        $std->xMunEnv = $cte->municipio_inicio ?? $cte->empresa->municipio ?? '';
        $std->UFEnv   = $cte->uf_inicio ?? $cte->emitente_uf ?? 'SP';
        $std->modal   = '01';
        $std->tpServ  = (int) ($cte->tipo_servico ?? 0);
        $std->cMunIni = $cte->codigo_municipio_inicio ?? '3550308';
        $std->xMunIni = $cte->municipio_inicio ?? '';
        $std->UFIni   = $cte->uf_inicio ?? 'SP';
        $std->cMunFim = $cte->codigo_municipio_fim ?? '3304557';
        $std->xMunFim = $cte->municipio_fim ?? '';
        $std->UFFim   = $cte->uf_fim ?? 'RJ';
        $std->retira  = 1;
        $std->indIEToma = $this->indicadorIEToma($cte);
        $this->make->tagide($std);
    }

    private function addTomador(Cte $cte): void
    {
        $toma = (int) ($cte->tomador ?? 3);

        if ($toma <= 3) {
            $std = new \stdClass();
            $std->toma = $toma;
            $this->make->tagtoma3($std);
        } else {
            $pessoa = $cte->tomadorPessoa;
            $std = new \stdClass();
            $std->toma  = 4;
            $std->CNPJ  = $pessoa ? preg_replace('/\D/', '', $pessoa->cnpj ?? '') : null;
            $std->CPF   = $pessoa && ! $pessoa->cnpj ? preg_replace('/\D/', '', $pessoa->cpf ?? '') : null;
            $std->IE    = $pessoa?->ie;
            $std->xNome = $pessoa?->nome ?? '';
            $std->xFant = $pessoa?->nome_fantasia;
            $std->fone  = $pessoa ? preg_replace('/\D/', '', $pessoa->telefone ?? '') : null;
            $std->email = $pessoa?->email;
            $this->make->tagtoma4($std);

            if ($pessoa) {
                $this->addEnderecoTomador($pessoa);
            }
        }
    }

    private function addEnderecoTomador($pessoa): void
    {
        $std = new \stdClass();
        $std->xLgr    = $pessoa->logradouro ?? '';
        $std->nro     = $pessoa->numero ?? 'SN';
        $std->xCpl    = $pessoa->complemento;
        $std->xBairro = $pessoa->bairro ?? '';
        $std->cMun    = $pessoa->codigo_municipio ?? '';
        $std->xMun    = $pessoa->municipio ?? '';
        $std->CEP     = preg_replace('/\D/', '', $pessoa->cep ?? '');
        $std->UF      = $pessoa->uf ?? '';
        $std->cPais   = '1058';
        $std->xPais   = 'BRASIL';
        $this->make->tagtomaEnder($std);
    }

    private function addEmitente(Cte $cte): void
    {
        $empresa = $cte->empresa;
        $std = new \stdClass();
        $std->CNPJ  = preg_replace('/\D/', '', $cte->emitente_cnpj ?? $empresa->cnpj ?? '');
        $std->IE    = preg_replace('/\D/', '', $cte->emitente_ie ?? $empresa->ie ?? '');
        $std->xNome = $cte->emitente_razao_social ?? $empresa->razao_social;
        $std->xFant = $empresa->nome_fantasia;
        $this->make->tagemit($std);

        $std = new \stdClass();
        $std->xLgr    = $empresa->logradouro ?? '';
        $std->nro     = $empresa->numero ?? 'SN';
        $std->xCpl    = $empresa->complemento;
        $std->xBairro = $empresa->bairro ?? '';
        $std->cMun    = $empresa->codigo_municipio ?? '';
        $std->xMun    = $empresa->municipio ?? '';
        $std->CEP     = preg_replace('/\D/', '', $empresa->cep ?? '');
        $std->UF      = $empresa->uf ?? '';
        $std->fone    = preg_replace('/\D/', '', $empresa->telefone ?? '');
        $this->make->tagenderEmit($std);
    }

    private function addRemetente(Cte $cte): void
    {
        if (! $cte->remetente_cnpj_cpf && ! $cte->remetente) return;

        $rem = $cte->remetente;
        $doc = preg_replace('/\D/', '', $cte->remetente_cnpj_cpf ?? $rem?->documentoFormatado() ?? '');

        $std = new \stdClass();
        if (strlen($doc) === 14) {
            $std->CNPJ = $doc;
        } else {
            $std->CPF = $doc;
        }
        $std->IE    = $cte->remetente_ie ?? $rem?->ie;
        $std->xNome = $cte->remetente_nome ?? $rem?->nome ?? '';
        $std->xFant = $rem?->nome_fantasia;
        $std->fone  = $rem ? preg_replace('/\D/', '', $rem->telefone ?? '') : null;
        $std->email = $rem?->email;
        $this->make->tagrem($std);

        $this->addEnderecoParticipante('tagenderReme', $cte->remetente_endereco, $rem);
    }

    private function addDestinatario(Cte $cte): void
    {
        if (! $cte->destinatario_cnpj_cpf && ! $cte->destinatario) return;

        $dest = $cte->destinatario;
        $doc = preg_replace('/\D/', '', $cte->destinatario_cnpj_cpf ?? $dest?->documentoFormatado() ?? '');

        $std = new \stdClass();
        if (strlen($doc) === 14) {
            $std->CNPJ = $doc;
        } else {
            $std->CPF = $doc;
        }
        $std->IE    = $cte->destinatario_ie ?? $dest?->ie;
        $std->xNome = $cte->destinatario_nome ?? $dest?->nome ?? '';
        $std->fone  = $dest ? preg_replace('/\D/', '', $dest->telefone ?? '') : null;
        $std->ISUF  = null;
        $std->email = $dest?->email;
        $this->make->tagdest($std);

        $this->addEnderecoParticipante('tagenderDest', $cte->destinatario_endereco, $dest);
    }

    private function addEnderecoParticipante(string $tag, ?array $endereco, $pessoa): void
    {
        $end = $endereco ? (object) $endereco : $pessoa;
        if (! $end) return;

        $std = new \stdClass();
        $std->xLgr    = $end->logradouro ?? '';
        $std->nro     = $end->numero ?? 'SN';
        $std->xCpl    = $end->complemento ?? null;
        $std->xBairro = $end->bairro ?? '';
        $std->cMun    = $end->codigo_municipio ?? '';
        $std->xMun    = $end->municipio ?? '';
        $std->CEP     = preg_replace('/\D/', '', $end->cep ?? '');
        $std->UF      = $end->uf ?? '';
        $std->cPais   = '1058';
        $std->xPais   = 'BRASIL';
        $this->make->{$tag}($std);
    }

    private function addValores(Cte $cte): void
    {
        $std = new \stdClass();
        $std->vTPrest = number_format($cte->valor_total_servico ?? 0, 2, '.', '');
        $std->vRec    = number_format($cte->valor_receber ?? $cte->valor_total_servico ?? 0, 2, '.', '');
        $this->make->tagvPrest($std);

        if ($cte->componentes && $cte->componentes->isNotEmpty()) {
            foreach ($cte->componentes as $comp) {
                $compStd = new \stdClass();
                $compStd->xNome = $comp->nome;
                $compStd->vComp = number_format($comp->valor, 2, '.', '');
                $this->make->tagComp($compStd);
            }
        } else {
            $compStd = new \stdClass();
            $compStd->xNome = 'FRETE VALOR';
            $compStd->vComp = number_format($cte->valor_total_servico ?? 0, 2, '.', '');
            $this->make->tagComp($compStd);
        }
    }

    private function addImpostos(Cte $cte): void
    {
        $std = new \stdClass();
        $std->vTotTrib = null;
        $std->infAdFisco = $cte->informacoes_fisco;
        $this->make->tagimp($std);

        $cst = $cte->cst_icms ?? '00';

        switch ($cst) {
            case '00':
                $icms = new \stdClass();
                $icms->cst   = '00';
                $icms->vBC   = number_format($cte->base_calc_icms ?? $cte->valor_total_servico ?? 0, 2, '.', '');
                $icms->pICMS = number_format($cte->aliquota_icms ?? 0, 2, '.', '');
                $icms->vICMS = number_format($cte->valor_icms ?? 0, 2, '.', '');
                $this->make->tagicms($icms);
                break;
            case '20':
                $icms = new \stdClass();
                $icms->cst    = '20';
                $icms->pRedBC = number_format($cte->percentual_reducao_bc ?? 0, 2, '.', '');
                $icms->vBC    = number_format($cte->base_calc_icms ?? 0, 2, '.', '');
                $icms->pICMS  = number_format($cte->aliquota_icms ?? 0, 2, '.', '');
                $icms->vICMS  = number_format($cte->valor_icms ?? 0, 2, '.', '');
                $this->make->tagicms($icms);
                break;
            case '40':
            case '41':
            case '51':
                $icms = new \stdClass();
                $icms->cst = $cst;
                $this->make->tagicms($icms);
                break;
            case '90':
                $icms = new \stdClass();
                $icms->cst   = '90';
                $icms->indSN = 1;
                $this->make->tagicms($icms);
                break;
            default:
                $icms = new \stdClass();
                $icms->cst   = '00';
                $icms->vBC   = number_format($cte->valor_total_servico ?? 0, 2, '.', '');
                $icms->pICMS = '0.00';
                $icms->vICMS = '0.00';
                $this->make->tagicms($icms);
        }
    }

    private function addCarga(Cte $cte): void
    {
        $std = new \stdClass();
        $std->vCarga      = number_format($cte->valor_carga ?? 0, 2, '.', '');
        $std->proPred     = $cte->produto_predominante ?? 'DIVERSOS';
        $std->xOutCat     = $cte->outras_caracteristicas;
        $std->vCargaAverb = null;
        $this->make->taginfCarga($std);

        $qStd = new \stdClass();
        $qStd->cUnid  = $cte->carga_unidade_medida ?? '01';
        $qStd->tpMed  = $cte->carga_tipo_medida ?? 'PESO BRUTO';
        $qStd->qCarga = number_format($cte->valor_total_mercadoria ?? 1, 4, '.', '');
        $this->make->taginfQ($qStd);
    }

    private function addDocumentosTransportados(Cte $cte): void
    {
        if ($cte->nfes_referenciadas) {
            foreach ($cte->nfes_referenciadas as $chave) {
                $std = new \stdClass();
                $std->chave = preg_replace('/\D/', '', $chave);
                $this->make->taginfNFe($std);
            }
        }

        foreach ($cte->documentos as $doc) {
            if ($doc->tipo === 'nfe' && $doc->chave_nfe) {
                $std = new \stdClass();
                $std->chave = preg_replace('/\D/', '', $doc->chave_nfe);
                $this->make->taginfNFe($std);
            } elseif ($doc->tipo === 'outros') {
                $std = new \stdClass();
                $std->tpDoc      = '00';
                $std->descOutros = $doc->numero;
                $std->nDoc       = $doc->numero;
                $std->dEmi       = $doc->data_emissao?->format('Y-m-d');
                $std->vDocFisc   = number_format($doc->valor ?? 0, 2, '.', '');
                $this->make->taginfOutros($std);
            }
        }
    }

    private function addModalRodoviario(Cte $cte): void
    {
        $std = new \stdClass();
        $std->RNTRC = preg_replace('/\D/', '', $cte->rntrc ?? $cte->emitente_rntrc ?? $cte->empresa->rntrc ?? '');
        $this->make->tagrodo($std);

        if ($cte->occ_numero) {
            $occStd = new \stdClass();
            $occStd->serie = null;
            $occStd->nOcc  = $cte->occ_numero;
            $occStd->dEmi  = $cte->occ_data_emissao?->format('Y-m-d');
            $occStd->CNPJ  = preg_replace('/\D/', '', $cte->occ_emitente ?? '');
            $this->make->tagoccRodo($occStd);
        }
    }

    private function addCteReferenciado(Cte $cte): void
    {
        $tipoCt = (int) ($cte->tipo_ct ?? 0);
        $chaveRef = $cte->cte_referenciado_chave ?? null;

        if ($tipoCt === 1 && $chaveRef) {
            $std = new \stdClass();
            $std->chCTe = preg_replace('/\D/', '', $chaveRef);
            $this->make->taginfCteComp($std);
        } elseif ($tipoCt === 3 && $chaveRef) {
            $std = new \stdClass();
            $std->chCte    = preg_replace('/\D/', '', $chaveRef);
            $std->tomaICMS = null;
            $this->make->taginfCteSub($std);
        }
    }

    private function addInformacoesAdicionais(Cte $cte): void
    {
        if (! $cte->informacoes_complementares) return;

        $std = new \stdClass();
        $std->infAdFisco = $cte->informacoes_fisco;
        $std->infCpl     = $cte->informacoes_complementares;
        $this->make->taginfCteNorm($std);
    }

    private function indicadorIEToma(Cte $cte): int
    {
        $toma = (int) ($cte->tomador ?? 3);
        $pessoa = match ($toma) {
            0 => $cte->remetente,
            3 => $cte->destinatario,
            default => $cte->tomadorPessoa,
        };
        return $pessoa?->ie ? 1 : 9;
    }

    private function codigoUF(string $uf): int
    {
        $ufs = [
            'AC'=>12,'AL'=>27,'AM'=>13,'AP'=>16,'BA'=>29,'CE'=>23,'DF'=>53,
            'ES'=>32,'GO'=>52,'MA'=>21,'MG'=>31,'MS'=>50,'MT'=>51,'PA'=>15,
            'PB'=>25,'PE'=>26,'PI'=>22,'PR'=>41,'RJ'=>33,'RN'=>24,'RO'=>11,
            'RR'=>14,'RS'=>43,'SC'=>42,'SE'=>28,'SP'=>35,'TO'=>17,
        ];
        return $ufs[strtoupper($uf)] ?? 35;
    }
}
