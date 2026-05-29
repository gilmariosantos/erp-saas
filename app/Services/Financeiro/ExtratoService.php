<?php

namespace App\Services\Financeiro;

use App\Models\ContaBancaria;
use App\Models\ExtratoBancario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de extrato bancário e conciliação.
 *
 * Responsável por:
 *  - Registrar movimentações no extrato
 *  - Importar arquivos OFX (Open Financial Exchange) dos bancos
 *  - Conciliar lançamentos do sistema com movimentações bancárias
 *  - Recalcular saldos
 */
class ExtratoService
{
    /**
     * Registra uma movimentação no extrato da conta.
     */
    public function registrar(
        ContaBancaria $conta,
        string        $tipo,          // 'credito' | 'debito'
        float         $valor,
        string        $data,
        ?string       $descricao = null,
        ?string       $origemTipo = null,
        ?int          $origemId = null,
    ): ExtratoBancario {
        $saldoAtual = $conta->fresh()->saldo_atual;

        return ExtratoBancario::create([
            'conta_bancaria_id' => $conta->id,
            'data_movimento'    => $data,
            'tipo'              => $tipo,
            'valor'             => $valor,
            'saldo_apos'        => $saldoAtual,
            'descricao'         => $descricao,
            'origem_tipo'       => $origemTipo,
            'origem_id'         => $origemId,
        ]);
    }

    /**
     * Importa arquivo OFX e retorna movimentações parseadas.
     *
     * Suporta OFX 1.x (SGML) e 2.x (XML) — padrão dos bancos brasileiros.
     *
     * @param  string $conteudoOfx  Conteúdo bruto do arquivo .ofx
     * @return Collection<array{
     *   data: string,
     *   tipo: string,
     *   valor: float,
     *   descricao: string,
     *   id_transacao: string,
     * }>
     */
    public function importarOfx(string $conteudoOfx): Collection
    {
        // Normaliza OFX 1.x (SGML sem fechamento de tags) para XML parseável
        $xml = $this->normalizarOfx($conteudoOfx);

        try {
            $dom = new \SimpleXMLElement($xml, LIBXML_NOERROR | LIBXML_RECOVER);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Arquivo OFX inválido ou corrompido: ' . $e->getMessage());
        }

        $transacoes = collect();

        // Navega até BANKTRANLIST (padrão OFX)
        $stmtrs = $dom->BANKMSGSRSV1->STMTTRNRS->STMTRS
            ?? $dom->CREDITCARDMSGSRSV1->CCSTMTTRNRS->CCSTMTRS
            ?? null;

        if (! $stmtrs) {
            throw new \InvalidArgumentException('Estrutura OFX não reconhecida. Verifique o arquivo.');
        }

        foreach ($stmtrs->BANKTRANLIST->STMTTRN ?? [] as $trn) {
            $tipo = strtolower((string) $trn->TRNTYPE);
            $valor = abs((float) $trn->TRNAMT);

            $transacoes->push([
                'data'         => $this->parseDataOfx((string) $trn->DTPOSTED),
                'tipo'         => in_array($tipo, ['credit', 'dep', 'directdep', 'int']) ? 'credito' : 'debito',
                'valor'        => $valor,
                'descricao'    => $this->limparDescricaoOfx((string) $trn->MEMO ?: (string) $trn->NAME),
                'id_transacao' => (string) $trn->FITID,
            ]);
        }

        Log::info('OFX importado', ['total_transacoes' => $transacoes->count()]);

        return $transacoes->sortBy('data')->values();
    }

    /**
     * Concilia movimentações OFX com lançamentos do sistema.
     *
     * Estratégia de matching (ordem de prioridade):
     *  1. Valor exato + data ±3 dias + FITID
     *  2. Valor exato + data ±3 dias
     *  3. Valor exato + data ±7 dias (sugestão)
     *
     * @param  Collection $transacoesOfx  Retorno de importarOfx()
     * @param  int        $contaId
     * @return array{
     *   conciliados: Collection,
     *   nao_encontrados: Collection,
     *   sugestoes: Collection,
     * }
     */
    public function conciliar(Collection $transacoesOfx, int $contaId): array
    {
        $conciliados    = collect();
        $naoEncontrados = collect();
        $sugestoes      = collect();

        foreach ($transacoesOfx as $trn) {
            $data = \Carbon\Carbon::parse($trn['data']);

            // Busca lançamento correspondente
            $lancamento = \App\Models\Lancamento::where('conta_bancaria_id', $contaId)
                ->where('tipo', $trn['tipo'] === 'credito' ? 'receber' : 'pagar')
                ->whereBetween('data_vencimento', [$data->subDays(3)->toDateString(), $data->addDays(3)->toDateString()])
                ->whereRaw('ABS(valor_original - ?) < 0.01', [$trn['valor']])
                ->whereIn('status', ['aberto', 'parcial'])
                ->first();

            if ($lancamento) {
                $conciliados->push([
                    'transacao'    => $trn,
                    'lancamento'   => $lancamento,
                    'confianca'    => 'alta',
                ]);
            } else {
                // Tenta com janela maior (sugestão)
                $sugestao = \App\Models\Lancamento::where('conta_bancaria_id', $contaId)
                    ->where('tipo', $trn['tipo'] === 'credito' ? 'receber' : 'pagar')
                    ->whereBetween('data_vencimento', [$data->subDays(7)->toDateString(), $data->addDays(7)->toDateString()])
                    ->whereRaw('ABS(valor_original - ?) < 0.01', [$trn['valor']])
                    ->whereIn('status', ['aberto', 'parcial'])
                    ->first();

                if ($sugestao) {
                    $sugestoes->push([
                        'transacao'  => $trn,
                        'lancamento' => $sugestao,
                        'confianca'  => 'media',
                    ]);
                } else {
                    $naoEncontrados->push($trn);
                }
            }
        }

        return compact('conciliados', 'naoEncontrados', 'sugestoes');
    }

    /**
     * Marca movimentações como conciliadas após confirmação do usuário.
     */
    public function confirmarConciliacao(Collection $conciliados, int $userId): void
    {
        DB::transaction(function () use ($conciliados, $userId) {
            foreach ($conciliados as $item) {
                ExtratoBancario::updateOrCreate(
                    ['conta_bancaria_id' => $item['lancamento']->conta_bancaria_id, 'documento' => $item['transacao']['id_transacao']],
                    [
                        'data_movimento'  => $item['transacao']['data'],
                        'tipo'            => $item['transacao']['tipo'],
                        'valor'           => $item['transacao']['valor'],
                        'saldo_apos'      => 0, // recalculado
                        'descricao'       => $item['transacao']['descricao'],
                        'origem_tipo'     => 'lancamento',
                        'origem_id'       => $item['lancamento']->id,
                        'conciliado'      => true,
                        'conciliado_em'   => now(),
                        'conciliado_por'  => $userId,
                    ]
                );
            }
        });
    }

    // ─── Privados ─────────────────────────────────────────────────────────────

    private function normalizarOfx(string $ofx): string
    {
        // Remove BOM e cabeçalho SGML do OFX 1.x
        $ofx = preg_replace('/^[\xEF\xBB\xBF]/', '', $ofx);
        $ofx = preg_replace('/[\r\n]+/', "\n", $ofx);

        // Se já é XML (OFX 2.x), retorna direto
        if (str_starts_with(ltrim($ofx), '<?xml')) {
            return $ofx;
        }

        // OFX 1.x: remove cabeçalho de linhas KEY:VALUE
        $ofx = preg_replace('/^.*?<OFX>/s', '<OFX>', $ofx);

        // Fecha tags SGML abertas (OFX 1.x não fecha todas as tags)
        $ofx = preg_replace('/<([A-Z0-9_]+)>([^<]+?)(\s*<\/\1>)?/m', '<$1>$2</$1>', $ofx);

        return '<?xml version="1.0" encoding="UTF-8"?>' . $ofx;
    }

    private function parseDataOfx(string $data): string
    {
        // OFX: YYYYMMDDHHMMSS[.xxx][+/-HH:mm]
        $data = preg_replace('/[\.\[\+\-].*/', '', $data);
        return \Carbon\Carbon::createFromFormat('YmdHis', str_pad($data, 14, '0'))->toDateString();
    }

    private function limparDescricaoOfx(string $descricao): string
    {
        $descricao = html_entity_decode($descricao, ENT_QUOTES, 'UTF-8');
        $descricao = preg_replace('/\s+/', ' ', $descricao);
        return substr(trim($descricao), 0, 200);
    }
}
