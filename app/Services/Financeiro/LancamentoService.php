<?php

namespace App\Services\Financeiro;

use App\Enums\LancamentoStatus;
use App\Models\ContaBancaria;
use App\Models\Lancamento;
use App\Models\LancamentoBaixa;
use App\Models\Transferencia;
use Brick\Money\Money;
use Brick\Money\Currency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Serviço de lançamentos financeiros (Contas a Pagar e Receber).
 *
 * Responsável por:
 *  - Criar lançamentos avulsos e parcelados
 *  - Registrar baixas (pagamentos) totais e parciais
 *  - Calcular juros e multas por atraso
 *  - Transferências entre contas
 *  - Atualizar saldos bancários atomicamente
 *  - Renegociação de dívidas
 */
class LancamentoService
{
    /** Multa padrão por atraso: 2% ao mês (Código Civil) */
    private const MULTA_PADRAO = 2.0;

    /** Juros padrão por atraso: 1% ao mês */
    private const JUROS_PADRAO_MENSAL = 1.0;

    public function __construct(
        private readonly ExtratoService $extratoService,
    ) {}

    // ─── Criação ─────────────────────────────────────────────────────────────

    /**
     * Cria um lançamento avulso.
     */
    public function criar(array $dados): Lancamento
    {
        return DB::transaction(function () use ($dados) {
            $lancamento = Lancamento::create([
                ...$dados,
                'status' => LancamentoStatus::ABERTO,
                'valor_aberto' => $dados['valor_original'],
                'parcela_numero' => 1,
                'parcela_total' => 1,
            ]);

            Log::info('Lançamento criado', [
                'id' => $lancamento->id,
                'tipo' => $lancamento->tipo,
                'valor' => $lancamento->valor_original,
            ]);

            return $lancamento;
        });
    }

    /**
     * Cria lançamento parcelado — gera N parcelas com grupo UUID compartilhado.
     *
     * @param array  $dados          Dados base do lançamento
     * @param int    $parcelas       Número de parcelas
     * @param string $intervaloDias  Intervalo entre vencimentos (ex: '30d', '15d')
     * @return Collection<Lancamento>
     */
    public function criarParcelado(array $dados, int $parcelas, string $intervaloDias = '30d'): Collection
    {
        if ($parcelas < 2 || $parcelas > 360) {
            throw new \InvalidArgumentException('Número de parcelas deve ser entre 2 e 360.');
        }

        $grupo = Str::uuid()->toString();
        $valorParcela = round($dados['valor_original'] / $parcelas, 2);
        $resto = round($dados['valor_original'] - ($valorParcela * $parcelas), 2);
        $dias = (int) filter_var($intervaloDias, FILTER_SANITIZE_NUMBER_INT);

        return DB::transaction(function () use ($dados, $parcelas, $dias, $grupo, $valorParcela, $resto) {
            $lancamentos = collect();
            $vencimento = \Carbon\Carbon::parse($dados['data_vencimento']);

            for ($i = 1; $i <= $parcelas; $i++) {
                // A última parcela absorve o centavo de arredondamento
                $valor = $i === $parcelas
                    ? $valorParcela + $resto
                    : $valorParcela;

                $lancamento = Lancamento::create([
                    ...$dados,
                    'valor_original' => $valor,
                    'data_vencimento' => $i === 1 ? $vencimento->toDateString() : $vencimento->addDays($dias)->toDateString(),
                    'status' => LancamentoStatus::ABERTO,
                    'grupo_parcelas' => $grupo,
                    'parcela_numero' => $i,
                    'parcela_total' => $parcelas,
                    'descricao' => $dados['descricao'] . " ({$i}/{$parcelas})",
                ]);

                $lancamentos->push($lancamento);
            }

            Log::info('Lançamento parcelado criado', [
                'grupo' => $grupo,
                'parcelas' => $parcelas,
                'total' => $dados['valor_original'],
            ]);

            return $lancamentos;
        });
    }

    // ─── Baixa / Pagamento ────────────────────────────────────────────────────

    /**
     * Registra baixa (pagamento/recebimento) de um lançamento.
     * Suporta pagamentos parciais — múltiplas baixas até quitar o saldo.
     *
     * @throws \InvalidArgumentException
     * @throws \OverflowException se valor pago exceder saldo aberto
     */
    public function baixar(
        Lancamento   $lancamento,
        ContaBancaria $conta,
        float         $valorPago,
        string        $dataPagamento,
        float         $valorJuros = 0,
        float         $valorMulta = 0,
        float         $valorDesconto = 0,
        ?int          $formaPagamentoId = null,
        ?string       $observacao = null,
    ): LancamentoBaixa {
        $this->validarBaixa($lancamento, $valorPago, $valorDesconto, $valorJuros, $valorMulta);

        return DB::transaction(function () use (
            $lancamento, $conta, $valorPago, $dataPagamento,
            $valorJuros, $valorMulta, $valorDesconto, $formaPagamentoId, $observacao
        ) {
            // 1. Registra a baixa
            $baixa = LancamentoBaixa::create([
                'lancamento_id'      => $lancamento->id,
                'conta_bancaria_id'  => $conta->id,
                'forma_pagamento_id' => $formaPagamentoId,
                'data_pagamento'     => $dataPagamento,
                'valor_pago'         => $valorPago,
                'valor_juros'        => $valorJuros,
                'valor_multa'        => $valorMulta,
                'valor_desconto'     => $valorDesconto,
                'observacao'         => $observacao,
            ]);

            // 2. Atualiza totais do lançamento
            $totalPagoNovo = $lancamento->valor_pago + $valorPago;
            $valorAberto = max(0, $lancamento->valor_original
                + $lancamento->valor_juros + $lancamento->valor_multa
                - $lancamento->valor_desconto - $totalPagoNovo
            );

            $status = match(true) {
                $valorAberto <= 0 => LancamentoStatus::PAGO,
                $totalPagoNovo > 0 => LancamentoStatus::PARCIAL,
                default => LancamentoStatus::ABERTO,
            };

            $lancamento->update([
                'valor_pago'     => $totalPagoNovo,
                'valor_juros'    => $lancamento->valor_juros + $valorJuros,
                'valor_multa'    => $lancamento->valor_multa + $valorMulta,
                'valor_desconto' => $lancamento->valor_desconto + $valorDesconto,
                'data_pagamento' => $valorAberto <= 0 ? $dataPagamento : $lancamento->data_pagamento,
                'status'         => $status,
            ]);

            // 3. Movimenta saldo da conta
            $this->movimentarSaldo($conta, $lancamento->tipo, $valorPago, $lancamento, $dataPagamento);

            Log::info('Baixa registrada', [
                'lancamento_id' => $lancamento->id,
                'valor_pago'    => $valorPago,
                'status'        => $status->value,
            ]);

            return $baixa;
        });
    }

    /**
     * Estorna (desfaz) uma baixa registrada.
     */
    public function estornarBaixa(LancamentoBaixa $baixa): void
    {
        DB::transaction(function () use ($baixa) {
            $lancamento = $baixa->lancamento;
            $conta = $baixa->contaBancaria;

            // Reverte saldo
            $tipoEstorno = $lancamento->tipo === 'receber' ? 'pagar' : 'receber';
            $this->movimentarSaldo($conta, $tipoEstorno, $baixa->valor_pago, $lancamento, today()->toDateString());

            // Atualiza lançamento
            $novoPago = max(0, $lancamento->valor_pago - $baixa->valor_pago);
            $lancamento->update([
                'valor_pago'     => $novoPago,
                'valor_juros'    => max(0, $lancamento->valor_juros - $baixa->valor_juros),
                'valor_multa'    => max(0, $lancamento->valor_multa - $baixa->valor_multa),
                'valor_desconto' => max(0, $lancamento->valor_desconto - $baixa->valor_desconto),
                'data_pagamento' => null,
                'status'         => $novoPago > 0 ? LancamentoStatus::PARCIAL : LancamentoStatus::ABERTO,
            ]);

            $baixa->delete();

            Log::info('Baixa estornada', ['baixa_id' => $baixa->id, 'lancamento_id' => $lancamento->id]);
        });
    }

    // ─── Transferência ────────────────────────────────────────────────────────

    /**
     * Transfere saldo entre duas contas do mesmo tenant.
     */
    public function transferir(
        ContaBancaria $origem,
        ContaBancaria $destino,
        float         $valor,
        string        $data,
        ?string       $descricao = null,
    ): Transferencia {
        if ($origem->id === $destino->id) {
            throw new \InvalidArgumentException('Conta de origem e destino devem ser diferentes.');
        }

        if ($valor <= 0) {
            throw new \InvalidArgumentException('Valor da transferência deve ser maior que zero.');
        }

        if ($origem->saldo_atual < $valor) {
            throw new \UnderflowException(
                "Saldo insuficiente na conta '{$origem->nome}'. "
                . "Saldo atual: R$ " . number_format($origem->saldo_atual, 2, ',', '.')
            );
        }

        return DB::transaction(function () use ($origem, $destino, $valor, $data, $descricao) {
            $transferencia = Transferencia::create([
                'empresa_id'         => $origem->empresa_id,
                'conta_origem_id'    => $origem->id,
                'conta_destino_id'   => $destino->id,
                'data_transferencia' => $data,
                'valor'              => $valor,
                'descricao'          => $descricao ?? "Transferência {$origem->nome} → {$destino->nome}",
            ]);

            // Débita origem
            $origem->decrement('saldo_atual', $valor);
            $this->extratoService->registrar($origem, 'debito', $valor, $data, $transferencia->descricao, 'transferencia', $transferencia->id);

            // Credita destino
            $destino->increment('saldo_atual', $valor);
            $this->extratoService->registrar($destino, 'credito', $valor, $data, $transferencia->descricao, 'transferencia', $transferencia->id);

            return $transferencia;
        });
    }

    // ─── Cálculos ─────────────────────────────────────────────────────────────

    /**
     * Calcula juros e multa por atraso para uma data de pagamento.
     *
     * @return array{juros: float, multa: float, total: float}
     */
    public function calcularEncargos(
        Lancamento $lancamento,
        string     $dataPagamento,
        float      $taxaJurosMensal = self::JUROS_PADRAO_MENSAL,
        float      $taxaMulta = self::MULTA_PADRAO,
    ): array {
        $vencimento = \Carbon\Carbon::parse($lancamento->data_vencimento);
        $pagamento  = \Carbon\Carbon::parse($dataPagamento);

        if ($pagamento->lte($vencimento)) {
            return ['juros' => 0.0, 'multa' => 0.0, 'total' => $lancamento->valor_original];
        }

        $diasAtraso = $vencimento->diffInDays($pagamento);
        $mesesAtraso = $diasAtraso / 30;

        $juros = round($lancamento->valor_original * ($taxaJurosMensal / 100) * $mesesAtraso, 2);
        $multa = round($lancamento->valor_original * ($taxaMulta / 100), 2);
        $total = round($lancamento->valor_original + $juros + $multa, 2);

        return compact('juros', 'multa', 'total');
    }

    /**
     * Retorna o fluxo de caixa projetado para um período.
     *
     * @return Collection<array{data: string, entradas: float, saidas: float, saldo: float}>
     */
    public function fluxoCaixa(int $empresaId, string $dataInicio, string $dataFim): Collection
    {
        $lancamentos = Lancamento::where('empresa_id', $empresaId)
            ->whereIn('status', ['aberto', 'parcial', 'vencido'])
            ->whereBetween('data_vencimento', [$dataInicio, $dataFim])
            ->select('tipo', 'data_vencimento', DB::raw('SUM(valor_original - valor_pago) as valor'))
            ->groupBy('tipo', 'data_vencimento')
            ->orderBy('data_vencimento')
            ->get();

        $porData = $lancamentos->groupBy('data_vencimento');
        $saldoAcumulado = 0.0;

        return $porData->map(function ($itens, $data) use (&$saldoAcumulado) {
            $entradas = (float) $itens->where('tipo', 'receber')->sum('valor');
            $saidas   = (float) $itens->where('tipo', 'pagar')->sum('valor');
            $saldoAcumulado += ($entradas - $saidas);

            return [
                'data'     => $data,
                'entradas' => round($entradas, 2),
                'saidas'   => round($saidas, 2),
                'saldo'    => round($saldoAcumulado, 2),
            ];
        })->values();
    }

    /**
     * Renegocia um grupo de lançamentos vencidos gerando novo parcelamento.
     */
    public function renegociar(Collection $lancamentos, array $novasCondicoes): Collection
    {
        if ($lancamentos->isEmpty()) {
            throw new \InvalidArgumentException('Nenhum lançamento selecionado para renegociação.');
        }

        $totalAberto = $lancamentos->sum(fn ($l) => $l->valor_original - $l->valor_pago);

        return DB::transaction(function () use ($lancamentos, $novasCondicoes, $totalAberto) {
            // Cancela originais
            $lancamentos->each(fn ($l) => $l->update(['status' => LancamentoStatus::RENEGOCIADO]));

            // Gera novo parcelamento
            $primeiro = $lancamentos->first();
            $dados = [
                'empresa_id'       => $primeiro->empresa_id,
                'tipo'             => $primeiro->tipo,
                'descricao'        => 'Renegociação — ' . $primeiro->descricao,
                'pessoa_id'        => $primeiro->pessoa_id,
                'data_emissao'     => today()->toDateString(),
                'data_vencimento'  => $novasCondicoes['primeiro_vencimento'],
                'valor_original'   => $totalAberto,
                'plano_conta_id'   => $primeiro->plano_conta_id,
                'centro_custo_id'  => $primeiro->centro_custo_id,
            ];

            return $this->criarParcelado(
                $dados,
                $novasCondicoes['parcelas'],
                $novasCondicoes['intervalo'] ?? '30d'
            );
        });
    }

    // ─── Privados ─────────────────────────────────────────────────────────────

    private function validarBaixa(
        Lancamento $lancamento,
        float $valorPago,
        float $desconto,
        float $juros,
        float $multa
    ): void {
        $erros = [];

        if ($valorPago <= 0) {
            $erros[] = 'Valor pago deve ser maior que zero.';
        }

        if (in_array($lancamento->status, [LancamentoStatus::PAGO, LancamentoStatus::CANCELADO])) {
            $erros[] = "Lançamento com status '{$lancamento->status->label()}' não pode receber baixa.";
        }

        $saldoAberto = $lancamento->valor_original - $lancamento->valor_pago + $juros + $multa - $desconto;
        if ($valorPago > round($saldoAberto + 0.01, 2)) {
            $erros[] = sprintf(
                'Valor pago (R$ %.2f) excede o saldo aberto (R$ %.2f).',
                $valorPago,
                $saldoAberto
            );
        }

        if (! empty($erros)) {
            throw new \InvalidArgumentException(implode(' ', $erros));
        }
    }

    private function movimentarSaldo(
        ContaBancaria $conta,
        string        $tipo,
        float         $valor,
        Lancamento    $lancamento,
        string        $data,
    ): void {
        if ($tipo === 'receber') {
            $conta->increment('saldo_atual', $valor);
            $this->extratoService->registrar($conta, 'credito', $valor, $data, $lancamento->descricao, 'lancamento', $lancamento->id);
        } else {
            $conta->decrement('saldo_atual', $valor);
            $this->extratoService->registrar($conta, 'debito', $valor, $data, $lancamento->descricao, 'lancamento', $lancamento->id);
        }
    }
}
