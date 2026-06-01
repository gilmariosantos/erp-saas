<?php

namespace App\Services\Fiscal;

use App\Models\Empresa;
use Illuminate\Support\Facades\Log;
use NFePHP\NFe\Tools as NFeTools;
use NFePHP\CTe\Tools as CTeTools;
use NFePHP\Common\Certificate;

/**
 * Camada de comunicação com a SEFAZ.
 * Centraliza toda a lógica de envio, retorno e parsing de respostas.
 *
 * Isola o sped-nfe/sped-cte do resto da aplicação — permite mock nos testes.
 */
class SefazService
{
    /**
     * Autoriza uma NF-e ou NFC-e.
     *
     * @param  string $xml    XML da NF-e montado e assinado
     * @param  Empresa $empresa Empresa emitente
     * @param  string $modelo '55' (NF-e) ou '65' (NFC-e)
     * @return array{cStat: int, xMotivo: string, chave: string|null, nProt: string|null, xml: string|null}
     */
    public function autorizar(string $xml, Empresa $empresa, string $modelo = '55'): array
    {
        $tools = $this->buildNfeTools($empresa, $modelo);

        try {
            $resp = $tools->sefazEnviaLote([$xml], 1);
            return $this->parseRetornoNfe($resp);
        } catch (\Throwable $e) {
            Log::error('Erro SEFAZ autorizar NF-e', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Cancela uma NF-e autorizada.
     */
    public function cancelar(
        string  $chave,
        string  $protocolo,
        string  $justificativa,
        Empresa $empresa,
        string  $modelo = '55'
    ): array {
        $tools = $this->buildNfeTools($empresa, $modelo);

        $resp = $tools->sefazCancela($chave, $justificativa, $protocolo);
        return $this->parseRetornoNfe($resp);
    }

    /**
     * Emite Carta de Correção Eletrônica (CC-e).
     */
    public function cartaCorrecao(
        string  $chave,
        int     $sequencia,
        string  $descricao,
        Empresa $empresa
    ): array {
        $tools = $this->buildNfeTools($empresa);
        $resp  = $tools->sefazCCe($chave, $descricao, $sequencia);
        return $this->parseRetornoNfe($resp);
    }

    /**
     * Consulta a situação de uma NF-e pelo número da chave.
     */
    public function consultarChave(string $chave, Empresa $empresa, string $modelo = '55'): array
    {
        $tools = $this->buildNfeTools($empresa, $modelo);
        $resp  = $tools->sefazConsultaChave($chave);
        return $this->parseRetornoNfe($resp);
    }

    /**
     * Inutiliza uma faixa de numeração.
     */
    public function inutilizar(
        Empresa $empresa,
        string  $serie,
        int     $inicio,
        int     $fim,
        string  $justificativa,
        string  $modelo = '55'
    ): array {
        $tools = $this->buildNfeTools($empresa, $modelo);
        $cnpj  = preg_replace('/\D/', '', $empresa->cnpj);
        $ano   = date('y');
        $resp  = $tools->sefazInutiliza($cnpj, $ano, $modelo, $serie, $inicio, $fim, $justificativa);
        return $this->parseRetornoNfe($resp);
    }

    /**
     * Autoriza um CT-e.
     */
    public function autorizarCte(string $xml, Empresa $empresa): array
    {
        $tools = $this->buildCTeTools($empresa);
        $resp  = $tools->sefazEnviaLote([$xml], 1);
        return $this->parseRetornoCte($resp);
    }

    /**
     * Cancela um CT-e autorizado.
     */
    public function cancelarCte(
        string  $chave,
        string  $protocolo,
        string  $justificativa,
        Empresa $empresa
    ): array {
        $tools = $this->buildCTeTools($empresa);
        $resp  = $tools->sefazCancela($chave, $justificativa, $protocolo);
        return $this->parseRetornoCte($resp);
    }

    // ─── Builders ─────────────────────────────────────────────────────────────

    private function buildNfeTools(Empresa $empresa, string $modelo = '55'): NFeTools
    {
        $config = $this->buildConfig($empresa, $modelo);
        $cert   = $this->loadCertificate($empresa);

        return new NFeTools($config, $cert);
    }

    private function buildCTeTools(Empresa $empresa): CTeTools
    {
        $config = $this->buildConfig($empresa, '57');
        $cert   = $this->loadCertificate($empresa);

        return new CTeTools($config, $cert);
    }

    private function buildConfig(Empresa $empresa, string $modelo): string
    {
        $uf  = $empresa->uf ?? 'SP';
        $cnpj = preg_replace('/\D/', '', $empresa->cnpj ?? '');

        $config = [
            'atualizacao' => date('Y-m-d H:i:s'),
            'tpAmb'       => $empresa->ambiente_nfe ?? 2,
            'razaosocial' => $empresa->razao_social,
            'cnpj'        => $cnpj,
            'siglaUF'     => $uf,
            'schemes'     => 'PL_009_V4',
            'versao'      => '4.00',
            'tokenIBPT'   => '',
            'CSC'         => $empresa->csc_token ?? '',
            'CSCid'       => $empresa->csc_id ?? '',
        ];

        return json_encode($config);
    }

    private function loadCertificate(Empresa $empresa): Certificate
    {
        if (empty($empresa->certificado_path)) {
            throw new \RuntimeException('Certificado digital não configurado para a empresa.');
        }

        $pfxContent = \Illuminate\Support\Facades\Storage::disk(
            config('fiscal.storage.disk', 's3')
        )->get($empresa->certificado_path);

        if (! $pfxContent) {
            throw new \RuntimeException('Arquivo de certificado não encontrado: ' . $empresa->certificado_path);
        }

        return Certificate::readPfx($pfxContent, decrypt($empresa->certificado_senha));
    }

    // ─── Parsers ──────────────────────────────────────────────────────────────

    private function parseRetornoNfe(string $resp): array
    {
        try {
            $dom = new \DOMDocument();
            $dom->loadXML($resp);

            $cStat  = $dom->getElementsByTagName('cStat')->item(0)?->nodeValue;
            $xMotivo= $dom->getElementsByTagName('xMotivo')->item(0)?->nodeValue;
            $chave  = $dom->getElementsByTagName('chNFe')->item(0)?->nodeValue;
            $nProt  = $dom->getElementsByTagName('nProt')->item(0)?->nodeValue;

            return [
                'cStat'   => (int) $cStat,
                'xMotivo' => $xMotivo ?? '',
                'chave'   => $chave,
                'nProt'   => $nProt,
                'xml'     => $resp,
            ];
        } catch (\Throwable $e) {
            Log::error('Erro ao parsear retorno SEFAZ', ['error' => $e->getMessage()]);
            return ['cStat' => 0, 'xMotivo' => $e->getMessage(), 'chave' => null, 'nProt' => null, 'xml' => null];
        }
    }

    private function parseRetornoCte(string $resp): array
    {
        return $this->parseRetornoNfe($resp); // Estrutura XML similar
    }

    /**
     * Consulta o status operacional do web service da SEFAZ.
     * Útil antes de emitir para evitar falhas por indisponibilidade.
     *
     * @return array{operacional: bool, cStat: int, motivo: string}
     */
    public function statusServico(Empresa $empresa): array
    {
        try {
            $tools = $this->buildNfeTools($empresa);
            $resp = $tools->sefazStatus();
            $parsed = $this->parseRetornoNfe($resp);

            return [
                'operacional' => (int) $parsed['cStat'] === 107,
                'cStat'       => $parsed['cStat'],
                'motivo'      => $parsed['xMotivo'],
            ];
        } catch (\Throwable $e) {
            return ['operacional' => false, 'cStat' => 0, 'motivo' => $e->getMessage()];
        }
    }

}
