<?php

namespace App\Services\Fiscal;

use App\Models\Empresa;
use App\Models\Nfse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Serviço de NFS-e — Nota Fiscal de Serviço Eletrônica.
 *
 * Suporta múltiplos padrões municipais via Strategy Pattern:
 *  - ABRASF 2.04 (padrão nacional — maioria dos municípios)
 *  - Paulistana (São Paulo - SP)
 *  - GINFES (Londrina, Maringá, e outros)
 *  - Betha (vários municípios do interior)
 *
 * Cada padrão tem seu próprio adaptador em NfseAdaptors/.
 * Para adicionar novo padrão: implementar NfseAdaptorInterface e registrar abaixo.
 */
class NfseService
{
    /** Mapa de padrão → classe adaptadora */
    private const ADAPTORES = [
        'abrasf'    => NfseAdaptors\AbrasflAdaptor::class,
        'paulistana'=> NfseAdaptors\PaulistanaAdaptor::class,
        'ginfes'    => NfseAdaptors\GinfesAdaptor::class,
        'betha'     => NfseAdaptors\BethaAdaptor::class,
    ];

    /**
     * Emite uma NFS-e usando o padrão municipal configurado na empresa.
     */
    public function emitir(Nfse $nfse): Nfse
    {
        $this->validar($nfse);

        return DB::transaction(function () use ($nfse) {
            $nfse->update(['status' => 'processando']);

            try {
                $adaptor = $this->resolverAdaptor($nfse->padrao_municipal);
                $retorno = $adaptor->emitir($nfse);

                $nfse->update([
                    'status'               => 'autorizada',
                    'numero'               => data_get($retorno, 'numero'),
                    'numero_verificacao'   => data_get($retorno, 'numero_verificacao'),
                    'codigo_verificacao'   => data_get($retorno, 'codigo_verificacao'),
                    'link_nfse'            => data_get($retorno, 'link'),
                    'xml_retorno'          => data_get($retorno, 'xml'),
                ]);

                $this->armazenar($nfse);

                Log::info('NFS-e emitida', [
                    'nfse_id'         => $nfse->id,
                    'numero'          => $nfse->numero,
                    'padrao'          => $nfse->padrao_municipal,
                    'municipio'       => $nfse->codigo_municipio,
                ]);

                return $nfse->fresh();

            } catch (\Throwable $e) {
                $nfse->update([
                    'status'          => 'rejeitada',
                    'motivo_rejeicao' => substr($e->getMessage(), 0, 500),
                ]);

                Log::error('Erro ao emitir NFS-e', [
                    'nfse_id' => $nfse->id,
                    'erro'    => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Cancela uma NFS-e autorizada.
     */
    public function cancelar(Nfse $nfse, string $motivo): Nfse
    {
        if ($nfse->status !== 'autorizada') {
            throw new \InvalidArgumentException('Apenas NFS-e autorizadas podem ser canceladas.');
        }

        return DB::transaction(function () use ($nfse, $motivo) {
            $adaptor = $this->resolverAdaptor($nfse->padrao_municipal);
            $retorno = $adaptor->cancelar($nfse, $motivo);

            $nfse->update([
                'status'            => 'cancelada',
                'xml_cancelamento'  => data_get($retorno, 'xml'),
            ]);

            return $nfse->fresh();
        });
    }

    /**
     * Detecta automaticamente o padrão municipal pelo código IBGE do município.
     * Simplifica o cadastro da empresa — não precisa informar o padrão manualmente.
     */
    public function detectarPadrao(string $codigoMunicipio): string
    {
        // Mapa de municípios por padrão (principal referência: nfephp-org/sped-nfse)
        $paulistana = ['3550308']; // São Paulo

        $ginfes = [
            '4113700', // Londrina
            '4115200', // Maringá
            '4104808', // Cascavel
        ];

        $betha = [
            '4202404', // Blumenau
            '4205407', // Florianópolis
            '4218400', // São José (SC)
        ];

        if (in_array($codigoMunicipio, $paulistana)) return 'paulistana';
        if (in_array($codigoMunicipio, $ginfes))     return 'ginfes';
        if (in_array($codigoMunicipio, $betha))      return 'betha';

        return 'abrasf'; // padrão nacional
    }

    // ─── Cálculo de impostos ──────────────────────────────────────────────────

    /**
     * Calcula os valores de retenção baseado na empresa e no tomador.
     *
     * @return array{valor_pis, valor_cofins, valor_ir, valor_csll, valor_inss, valor_iss, valor_liquido}
     */
    public function calcularImpostos(
        float   $valorServico,
        Empresa $empresa,
        float   $aliquotaIss = null,
        bool    $issRetido = false,
        bool    $pisRetido = false,
        bool    $cofinsRetido = false,
        bool    $irRetido = false,
        bool    $csllRetido = false,
        bool    $inssRetido = false,
    ): array {
        $aliquota = $aliquotaIss ?? (float) $empresa->aliquota_iss ?? 0;

        $iss    = $issRetido   ? round($valorServico * ($aliquota / 100), 2) : 0;
        $pis    = $pisRetido   ? round($valorServico * 0.0065, 2) : 0;
        $cofins = $cofinsRetido? round($valorServico * 0.03, 2)   : 0;
        $ir     = $irRetido    ? round($valorServico * 0.015, 2)  : 0;
        $csll   = $csllRetido  ? round($valorServico * 0.01, 2)   : 0;
        $inss   = $inssRetido  ? round($valorServico * 0.11, 2)   : 0;

        $totalRetencoes = $iss + $pis + $cofins + $ir + $csll + $inss;
        $valorLiquido   = round($valorServico - $totalRetencoes, 2);

        return compact('iss', 'pis', 'cofins', 'ir', 'csll', 'inss', 'totalRetencoes', 'valorLiquido');
    }

    // ─── Privados ─────────────────────────────────────────────────────────────

    private function validar(Nfse $nfse): void
    {
        $erros = [];

        if (empty($nfse->empresa->cnpj)) {
            $erros[] = 'CNPJ da empresa não configurado.';
        }

        if (empty($nfse->empresa->codigo_tributacao_municipio) && empty($nfse->codigo_servico)) {
            $erros[] = 'Código de serviço não configurado.';
        }

        if ($nfse->valor_servico <= 0) {
            $erros[] = 'Valor do serviço deve ser maior que zero.';
        }

        if (! empty($erros)) {
            throw new \InvalidArgumentException(implode(' ', $erros));
        }
    }

    private function resolverAdaptor(string $padrao): NfseAdaptors\NfseAdaptorInterface
    {
        $classe = self::ADAPTORES[$padrao] ?? self::ADAPTORES['abrasf'];

        if (! class_exists($classe)) {
            throw new \RuntimeException("Adaptador NFS-e não encontrado para padrão: {$padrao}");
        }

        return app($classe);
    }

    private function armazenar(Nfse $nfse): void
    {
        if ($nfse->xml_retorno) {
            $path = "xmls/{$nfse->empresa->cnpjSemMascara()}/nfse/{$nfse->data_emissao?->format('Y/m')}/nfse_{$nfse->numero}.xml";
            Storage::disk('s3')->put($path, $nfse->xml_retorno, 'private');
            $nfse->update(['path_xml' => $path]);
        }
    }
}
