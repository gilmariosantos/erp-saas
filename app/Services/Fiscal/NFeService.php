<?php

namespace App\Services\Fiscal;

use App\Enums\NFeStatus;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeItem;
use App\Services\Fiscal\Contracts\FiscalDocumentInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\DA\NFe\Danfe;
use Throwable;

/**
 * Serviço de emissão, consulta e cancelamento de NF-e.
 *
 * Responsável por:
 *  - Montar o XML da NF-e a partir dos dados do banco
 *  - Enviar à SEFAZ (autorização, cancelamento, inutilização)
 *  - Armazenar XML e PDF no S3/MinIO
 *  - Tratar erros de rejeição e contingência
 *
 * @see https://github.com/nfephp-org/sped-nfe
 */
class NFeService implements FiscalDocumentInterface
{
    public function __construct(
        private readonly NfeXmlBuilder $xmlBuilder,
        private readonly SefazService $sefaz,
    ) {}

    /**
     * Emite (autoriza) uma NF-e na SEFAZ.
     *
     * @throws \Exception se falhar após todas as tentativas
     */
    public function emitir(Nfe $nfe): Nfe
    {
        $this->validarAntesDaEmissao($nfe);

        return DB::transaction(function () use ($nfe) {
            $nfe->update([
                'status' => NFeStatus::PROCESSANDO,
                'tentativas_envio' => $nfe->tentativas_envio + 1,
                'ultima_tentativa_em' => now(),
            ]);

            try {
                // 1. Monta XML
                $xml = $this->xmlBuilder->build($nfe);
                $nfe->update(['xml_enviado' => $xml]);

                // 2. Envia à SEFAZ
                $retorno = $this->sefaz->autorizar($xml, $nfe->empresa, $nfe->modelo);

                // 3. Processa retorno
                $this->processarRetornoAutorizacao($nfe, $retorno);

                // 4. Armazena XML autorizado no S3
                if ($nfe->status === NFeStatus::AUTORIZADA) {
                    $this->armazenarXml($nfe);
                    $this->gerarPdf($nfe);
                    $this->enviarEmailDestinatario($nfe);
                }

                Log::info('NF-e emitida com sucesso', [
                    'nfe_id' => $nfe->id,
                    'chave' => $nfe->chave_acesso,
                    'protocolo' => $nfe->protocolo_autorizacao,
                ]);

                return $nfe->fresh();

            } catch (Throwable $e) {
                $this->marcarComoRejeitada($nfe, $e->getMessage());

                Log::error('Erro ao emitir NF-e', [
                    'nfe_id' => $nfe->id,
                    'erro' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Cancela uma NF-e autorizada.
     *
     * @param int $prazoHoras prazo máximo é 24h (Simples Nacional) ou conforme legislação
     * @throws \Exception se NF-e não puder ser cancelada
     */
    public function cancelar(Nfe $nfe, string $justificativa, int $prazoHoras = 24): Nfe
    {
        if ($nfe->status !== NFeStatus::AUTORIZADA) {
            throw new \InvalidArgumentException(
                "Apenas NF-e autorizadas podem ser canceladas. Status atual: {$nfe->status->value}"
            );
        }

        if (strlen($justificativa) < 15) {
            throw new \InvalidArgumentException(
                'A justificativa de cancelamento deve ter no mínimo 15 caracteres.'
            );
        }

        $horasDesdeEmissao = $nfe->data_autorizacao->diffInHours(now());
        if ($horasDesdeEmissao > $prazoHoras) {
            throw new \InvalidArgumentException(
                "Prazo para cancelamento expirado ({$horasDesdeEmissao}h desde a autorização)."
            );
        }

        return DB::transaction(function () use ($nfe, $justificativa) {
            $retorno = $this->sefaz->cancelar(
                chave: $nfe->chave_acesso,
                protocolo: $nfe->protocolo_autorizacao,
                justificativa: $justificativa,
                empresa: $nfe->empresa,
                modelo: $nfe->modelo,
            );

            $nfe->update([
                'status' => NFeStatus::CANCELADA,
                'cancelada_em' => now(),
                'motivo_cancelamento' => $justificativa,
                'protocolo_cancelamento' => data_get($retorno, 'protocolo'),
                'xml_cancelamento' => data_get($retorno, 'xml'),
            ]);

            $this->armazenarXmlCancelamento($nfe);

            Log::info('NF-e cancelada', [
                'nfe_id' => $nfe->id,
                'chave' => $nfe->chave_acesso,
                'justificativa' => $justificativa,
            ]);

            return $nfe->fresh();
        });
    }

    /**
     * Emite Carta de Correção Eletrônica (CC-e).
     */
    public function cartaCorrecao(Nfe $nfe, string $descricao): Nfe
    {
        if ($nfe->status !== NFeStatus::AUTORIZADA) {
            throw new \InvalidArgumentException('CC-e só pode ser emitida para NF-e autorizada.');
        }

        if (strlen($descricao) < 15) {
            throw new \InvalidArgumentException('Descrição da correção deve ter no mínimo 15 caracteres.');
        }

        $sequencia = $nfe->cce_sequencia + 1;

        $retorno = $this->sefaz->cartaCorrecao(
            chave: $nfe->chave_acesso,
            sequencia: $sequencia,
            descricao: $descricao,
            empresa: $nfe->empresa,
        );

        $nfe->update([
            'cce_em' => now(),
            'cce_descricao' => $descricao,
            'cce_sequencia' => $sequencia,
            'protocolo_cce' => data_get($retorno, 'protocolo'),
            'xml_carta_correcao' => data_get($retorno, 'xml'),
        ]);

        return $nfe->fresh();
    }

    /**
     * Consulta situação de uma NF-e na SEFAZ.
     */
    public function consultar(Nfe $nfe): array
    {
        return $this->sefaz->consultarChave(
            chave: $nfe->chave_acesso,
            empresa: $nfe->empresa,
            modelo: $nfe->modelo,
        );
    }

    /**
     * Inutiliza uma faixa de numeração.
     */
    public function inutilizar(
        Empresa $empresa,
        string $serie,
        int $numeroInicio,
        int $numeroFim,
        string $justificativa,
        string $modelo = '55'
    ): array {
        if (strlen($justificativa) < 15) {
            throw new \InvalidArgumentException('Justificativa mínima de 15 caracteres.');
        }

        return $this->sefaz->inutilizar(
            empresa: $empresa,
            serie: $serie,
            inicio: $numeroInicio,
            fim: $numeroFim,
            justificativa: $justificativa,
            modelo: $modelo,
        );
    }

    // ─── Métodos privados ────────────────────────────────────────────────────

    private function validarAntesDaEmissao(Nfe $nfe): void
    {
        $erros = [];

        if (empty($nfe->empresa->cnpj)) {
            $erros[] = 'CNPJ do emitente não configurado.';
        }

        if (empty($nfe->empresa->certificado_path)) {
            $erros[] = 'Certificado digital não configurado.';
        }

        if ($nfe->empresa->certificado_validade && $nfe->empresa->certificado_validade->isPast()) {
            $erros[] = 'Certificado digital vencido em ' . $nfe->empresa->certificado_validade->format('d/m/Y') . '.';
        }

        if ($nfe->itens->isEmpty()) {
            $erros[] = 'NF-e não possui itens.';
        }

        if (! in_array($nfe->status, [NFeStatus::RASCUNHO, NFeStatus::REJEITADA])) {
            $erros[] = "NF-e com status '{$nfe->status->label()}' não pode ser emitida.";
        }

        if (! empty($erros)) {
            throw new \InvalidArgumentException(implode(' ', $erros));
        }
    }

    private function processarRetornoAutorizacao(Nfe $nfe, array $retorno): void
    {
        $cStat = (int) data_get($retorno, 'cStat', 0);

        // Códigos de autorização: 100 (autorizada), 150 (autorizada fora do prazo)
        if (in_array($cStat, [100, 150])) {
            $nfe->update([
                'status' => NFeStatus::AUTORIZADA,
                'chave_acesso' => data_get($retorno, 'chave'),
                'protocolo_autorizacao' => data_get($retorno, 'nProt'),
                'data_autorizacao' => now(),
                'xml_retorno' => data_get($retorno, 'xml'),
                'codigo_retorno' => $cStat,
                'descricao_retorno' => data_get($retorno, 'xMotivo'),
            ]);
        }
        // Denegada
        elseif (in_array($cStat, [110, 301, 302])) {
            $nfe->update([
                'status' => NFeStatus::DENEGADA,
                'codigo_retorno' => $cStat,
                'motivo_rejeicao' => data_get($retorno, 'xMotivo'),
                'xml_retorno' => data_get($retorno, 'xml'),
            ]);
        }
        // Rejeitada
        else {
            $nfe->update([
                'status' => NFeStatus::REJEITADA,
                'codigo_retorno' => $cStat,
                'motivo_rejeicao' => data_get($retorno, 'xMotivo'),
            ]);
        }
    }

    private function marcarComoRejeitada(Nfe $nfe, string $motivo): void
    {
        $nfe->update([
            'status' => NFeStatus::REJEITADA,
            'motivo_rejeicao' => substr($motivo, 0, 500),
        ]);
    }

    private function armazenarXml(Nfe $nfe): void
    {
        $path = "xmls/{$nfe->empresa->cnpj}/{$nfe->data_emissao->format('Y/m')}/nfe_{$nfe->chave_acesso}.xml";
        Storage::disk('s3')->put($path, $nfe->xml_retorno, 'private');
        $nfe->update(['path_xml' => $path]);
    }

    private function armazenarXmlCancelamento(Nfe $nfe): void
    {
        $path = "xmls/{$nfe->empresa->cnpj}/{$nfe->cancelada_em->format('Y/m')}/canc_{$nfe->chave_acesso}.xml";
        Storage::disk('s3')->put($path, $nfe->xml_cancelamento, 'private');
    }

    private function gerarPdf(Nfe $nfe): void
    {
        try {
            $danfe = new Danfe($nfe->xml_retorno);
            $pdf = $danfe->render();

            $path = "pdfs/{$nfe->empresa->cnpj}/{$nfe->data_emissao->format('Y/m')}/nfe_{$nfe->chave_acesso}.pdf";
            Storage::disk('s3')->put($path, $pdf, 'private');
            $nfe->update(['path_pdf' => $path]);
        } catch (Throwable $e) {
            Log::warning('Erro ao gerar DANFE', [
                'nfe_id' => $nfe->id,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    private function enviarEmailDestinatario(Nfe $nfe): void
    {
        $email = $nfe->destinatario_email
            ?? optional($nfe->destinatario)->email_nfe
            ?? optional($nfe->destinatario)->email;

        if ($email) {
            \App\Jobs\EnviarNfeEmail::dispatch($nfe, $email)->afterCommit();
        }
    }
}
