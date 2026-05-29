<?php

namespace App\Services\Fiscal;

use App\Enums\CTeStatus;
use App\Models\Cte;
use App\Models\Empresa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use NFePHP\CTe\Make;
use NFePHP\CTe\Tools;
use NFePHP\DA\CTe\Dacte;
use Throwable;

/**
 * Serviço de emissão de CT-e (leiaute 4.00) e integração CIOT com a ANTT.
 *
 * CIOT — Código Identificador da Operação de Transporte:
 *  - Obrigatório para contratação de autônomo (TAC) e pessoa jurídica não-registrada
 *  - Gerado via WebService da ANTT (www.antt.gov.br)
 *  - Deve constar no CT-e antes da emissão
 *
 * @see https://github.com/nfephp-org/sped-cte
 * @see https://www.antt.gov.br/backend/galeria/arquivos/2013/12/20/ManualwebserviceCIOT.pdf
 */
class CTeService
{
    /** Endpoint ANTT CIOT — produção */
    private const ANTT_ENDPOINT_PROD = 'https://ws.antt.gov.br/CIOT/ServicoCIOT.svc';

    /** Endpoint ANTT CIOT — homologação */
    private const ANTT_ENDPOINT_HOM = 'https://homologacao.antt.gov.br/CIOT/ServicoCIOT.svc';

    public function __construct(
        private readonly CTeXmlBuilder $xmlBuilder,
        private readonly SefazService $sefaz,
    ) {}

    /**
     * Emite um CT-e na SEFAZ.
     */
    public function emitir(Cte $cte): Cte
    {
        $this->validarAntesDaEmissao($cte);

        return DB::transaction(function () use ($cte) {
            $cte->update([
                'status' => CTeStatus::PROCESSANDO,
                'tentativas_envio' => $cte->tentativas_envio + 1,
            ]);

            try {
                $xml = $this->xmlBuilder->build($cte);
                $cte->update(['xml_enviado' => $xml]);

                $retorno = $this->sefaz->autorizarCte($xml, $cte->empresa);
                $this->processarRetorno($cte, $retorno);

                if ($cte->status === CTeStatus::AUTORIZADA) {
                    $this->armazenarXml($cte);
                    $this->gerarDacte($cte);
                }

                Log::info('CT-e emitido com sucesso', [
                    'cte_id' => $cte->id,
                    'chave' => $cte->chave_acesso,
                ]);

                return $cte->fresh();

            } catch (Throwable $e) {
                $cte->update([
                    'status' => CTeStatus::REJEITADA,
                    'motivo_rejeicao' => substr($e->getMessage(), 0, 500),
                ]);

                Log::error('Erro ao emitir CT-e', [
                    'cte_id' => $cte->id,
                    'erro' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Gera CIOT junto à ANTT para operações com autônomo.
     *
     * O CIOT é obrigatório quando:
     *  - O transportador for TAC (Transportador Autônomo de Cargas)
     *  - Qualquer operação de transporte com motoristas PF não vínculos CLT
     *
     * @param string $cpfCnpjContratado CPF do motorista ou CNPJ da transportadora
     * @param string $cpfCnpjContratante CNPJ da empresa contratante
     * @param float  $valorFrete         Valor do frete combinado
     * @param float  $valorPedagio       Valor do pedágio (se houver)
     * @param string $placaVeiculo       Placa do veículo principal
     * @param string $ufOrigem           UF de origem da carga
     * @param string $ufDestino          UF de destino
     *
     * @return array ['ciot' => '...', 'protocolo' => '...']
     */
    public function gerarCiot(
        string $cpfCnpjContratado,
        string $cpfCnpjContratante,
        float  $valorFrete,
        float  $valorPedagio,
        string $placaVeiculo,
        string $ufOrigem,
        string $ufDestino,
        Empresa $empresa,
    ): array {
        $ambiente = config('fiscal.sefaz.ambiente', 2);
        $endpoint = $ambiente === 1 ? self::ANTT_ENDPOINT_PROD : self::ANTT_ENDPOINT_HOM;

        $soapBody = $this->montarSoapCiot(
            acao: 'GerarCIOT',
            dados: [
                'CPFCNPJContratado' => preg_replace('/\D/', '', $cpfCnpjContratado),
                'CPFCNPJContratante' => preg_replace('/\D/', '', $cpfCnpjContratante),
                'ValorFrete' => number_format($valorFrete, 2, '.', ''),
                'ValorPedagio' => number_format($valorPedagio, 2, '.', ''),
                'PlacaVeiculo' => strtoupper(preg_replace('/[^A-Z0-9]/i', '', $placaVeiculo)),
                'UFOrigem' => $ufOrigem,
                'UFDestino' => $ufDestino,
                'DataInicioViagem' => now()->format('Y-m-d'),
            ]
        );

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => 'http://tempuri.org/IServicoCIOT/GerarCIOT',
            ])
            ->timeout(30)
            ->send('POST', $endpoint, ['body' => $soapBody]);

            if ($response->failed()) {
                throw new \RuntimeException('ANTT retornou erro HTTP: ' . $response->status());
            }

            $result = $this->parsearRespostaCiot($response->body());

            Log::info('CIOT gerado com sucesso', [
                'ciot' => $result['ciot'],
                'contratado' => $cpfCnpjContratado,
                'placa' => $placaVeiculo,
            ]);

            return $result;

        } catch (Throwable $e) {
            Log::error('Erro ao gerar CIOT', [
                'contratado' => $cpfCnpjContratado,
                'placa' => $placaVeiculo,
                'erro' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Consulta a situação de um CIOT na ANTT.
     */
    public function consultarCiot(string $ciot, Empresa $empresa): array
    {
        $ambiente = config('fiscal.sefaz.ambiente', 2);
        $endpoint = $ambiente === 1 ? self::ANTT_ENDPOINT_PROD : self::ANTT_ENDPOINT_HOM;

        $soapBody = $this->montarSoapCiot('ConsultarCIOT', ['CIOT' => $ciot]);

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => 'http://tempuri.org/IServicoCIOT/ConsultarCIOT',
        ])
        ->timeout(30)
        ->send('POST', $endpoint, ['body' => $soapBody]);

        return $this->parsearRespostaCiot($response->body());
    }

    /**
     * Cancela um CT-e autorizado.
     */
    public function cancelar(Cte $cte, string $justificativa): Cte
    {
        if ($cte->status !== CTeStatus::AUTORIZADA) {
            throw new \InvalidArgumentException('Apenas CT-e autorizado pode ser cancelado.');
        }

        if (strlen($justificativa) < 15) {
            throw new \InvalidArgumentException('Justificativa mínima de 15 caracteres.');
        }

        return DB::transaction(function () use ($cte, $justificativa) {
            $retorno = $this->sefaz->cancelarCte(
                chave: $cte->chave_acesso,
                protocolo: $cte->protocolo_autorizacao,
                justificativa: $justificativa,
                empresa: $cte->empresa,
            );

            $cte->update([
                'status' => CTeStatus::CANCELADA,
                'cancelada_em' => now(),
                'motivo_cancelamento' => $justificativa,
                'xml_cancelamento' => data_get($retorno, 'xml'),
            ]);

            Log::info('CT-e cancelado', ['cte_id' => $cte->id]);

            return $cte->fresh();
        });
    }

    // ─── Privados ────────────────────────────────────────────────────────────

    private function validarAntesDaEmissao(Cte $cte): void
    {
        $erros = [];

        if (empty($cte->empresa->certificado_path)) {
            $erros[] = 'Certificado digital não configurado.';
        }

        if (empty($cte->empresa->rntrc)) {
            $erros[] = 'RNTRC da empresa não configurado. Necessário para emissão de CT-e.';
        }

        if (empty($cte->remetente_cnpj_cpf)) {
            $erros[] = 'Remetente não informado.';
        }

        if (empty($cte->destinatario_cnpj_cpf)) {
            $erros[] = 'Destinatário não informado.';
        }

        if ($cte->valor_total_servico <= 0) {
            $erros[] = 'Valor do serviço deve ser maior que zero.';
        }

        if (! empty($erros)) {
            throw new \InvalidArgumentException(implode(' ', $erros));
        }
    }

    private function processarRetorno(Cte $cte, array $retorno): void
    {
        $cStat = (int) data_get($retorno, 'cStat', 0);

        if (in_array($cStat, [100, 150])) {
            $cte->update([
                'status' => CTeStatus::AUTORIZADA,
                'chave_acesso' => data_get($retorno, 'chave'),
                'protocolo_autorizacao' => data_get($retorno, 'nProt'),
                'data_autorizacao' => now(),
                'xml_retorno' => data_get($retorno, 'xml'),
                'codigo_retorno' => $cStat,
                'descricao_retorno' => data_get($retorno, 'xMotivo'),
            ]);
        } else {
            $cte->update([
                'status' => CTeStatus::REJEITADA,
                'codigo_retorno' => $cStat,
                'motivo_rejeicao' => data_get($retorno, 'xMotivo'),
            ]);
        }
    }

    private function armazenarXml(Cte $cte): void
    {
        $path = "xmls/{$cte->empresa->cnpj}/{$cte->data_emissao->format('Y/m')}/cte_{$cte->chave_acesso}.xml";
        Storage::disk('s3')->put($path, $cte->xml_retorno, 'private');
        $cte->update(['path_xml' => $path]);
    }

    private function gerarDacte(Cte $cte): void
    {
        try {
            $dacte = new Dacte($cte->xml_retorno);
            $pdf = $dacte->render();

            $path = "pdfs/{$cte->empresa->cnpj}/{$cte->data_emissao->format('Y/m')}/cte_{$cte->chave_acesso}.pdf";
            Storage::disk('s3')->put($path, $pdf, 'private');
            $cte->update(['path_pdf' => $path]);
        } catch (Throwable $e) {
            Log::warning('Erro ao gerar DACTE', ['cte_id' => $cte->id, 'erro' => $e->getMessage()]);
        }
    }

    private function montarSoapCiot(string $acao, array $dados): string
    {
        $campos = '';
        foreach ($dados as $chave => $valor) {
            $campos .= "<tem:{$chave}>{$valor}</tem:{$chave}>";
        }

        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <soapenv:Envelope
            xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
            xmlns:tem="http://tempuri.org/">
          <soapenv:Header/>
          <soapenv:Body>
            <tem:{$acao}>
              <tem:request>
                {$campos}
              </tem:request>
            </tem:{$acao}>
          </soapenv:Body>
        </soapenv:Envelope>
        XML;
    }

    private function parsearRespostaCiot(string $xml): array
    {
        try {
            $dom = new \DOMDocument();
            $dom->loadXML($xml);

            $ciot = $dom->getElementsByTagName('CIOT')->item(0)?->nodeValue ?? '';
            $protocolo = $dom->getElementsByTagName('Protocolo')->item(0)?->nodeValue ?? '';
            $status = $dom->getElementsByTagName('Status')->item(0)?->nodeValue ?? '';
            $descricao = $dom->getElementsByTagName('Descricao')->item(0)?->nodeValue ?? '';

            return compact('ciot', 'protocolo', 'status', 'descricao');
        } catch (Throwable $e) {
            throw new \RuntimeException('Erro ao parsear resposta ANTT: ' . $e->getMessage());
        }
    }
}
