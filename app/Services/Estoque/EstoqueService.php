<?php

namespace App\Services\Estoque;

use App\Models\LocalEstoque;
use App\Models\Lote;
use App\Models\MovimentacaoEstoque;
use App\Models\PedidoCompra;
use App\Models\PedidoCompraItem;
use App\Models\Produto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de movimentação de estoque.
 *
 * Método de custeio: Custo Médio Ponderado (CMP) — padrão fiscal brasileiro.
 *
 * Fórmula CMP:
 *   novo_custo_medio = (estoque_atual * custo_medio_atual + qtd_entrada * custo_entrada)
 *                      / (estoque_atual + qtd_entrada)
 */
class EstoqueService
{
    // ─── Movimentações ────────────────────────────────────────────────────────

    /**
     * Registra entrada de estoque e recalcula custo médio ponderado.
     */
    public function entrada(
        Produto      $produto,
        float        $quantidade,
        float        $custoUnitario,
        string       $data,
        string       $origemTipo = 'manual',
        ?int         $origemId = null,
        ?int         $localId = null,
        ?int         $loteId = null,
        ?string      $observacao = null,
    ): MovimentacaoEstoque {
        if ($quantidade <= 0) {
            throw new \InvalidArgumentException('Quantidade de entrada deve ser maior que zero.');
        }
        if ($custoUnitario < 0) {
            throw new \InvalidArgumentException('Custo unitário não pode ser negativo.');
        }

        return DB::transaction(function () use (
            $produto, $quantidade, $custoUnitario, $data,
            $origemTipo, $origemId, $localId, $loteId, $observacao
        ) {
            $saldoAnterior = $produto->estoque_atual;

            // Custo Médio Ponderado
            $novoCustoMedio = $this->calcularCmpEntrada(
                $produto->estoque_atual,
                $produto->preco_custo,
                $quantidade,
                $custoUnitario
            );

            // Atualiza produto
            $produto->increment('estoque_atual', $quantidade);
            $produto->update(['preco_custo' => $novoCustoMedio]);

            // Atualiza lote se informado
            if ($loteId) {
                Lote::where('id', $loteId)->increment('quantidade_atual', $quantidade);
            }

            $mov = MovimentacaoEstoque::create([
                'empresa_id'        => $produto->empresa_id ?? tenant('id'),
                'produto_id'        => $produto->id,
                'local_estoque_id'  => $localId,
                'lote_id'           => $loteId,
                'tipo'              => 'entrada',
                'origem_tipo'       => $origemTipo,
                'origem_id'         => $origemId,
                'quantidade'        => $quantidade,
                'custo_unitario'    => $custoUnitario,
                'saldo_anterior'    => $saldoAnterior,
                'saldo_posterior'   => $produto->fresh()->estoque_atual,
                'data_movimento'    => $data,
                'observacao'        => $observacao,
            ]);

            Log::info('Entrada de estoque', [
                'produto_id'  => $produto->id,
                'quantidade'  => $quantidade,
                'custo_medio' => $novoCustoMedio,
            ]);

            return $mov;
        });
    }

    /**
     * Registra saída de estoque.
     * Usa custo médio ponderado atual para valorização da saída.
     *
     * @throws \UnderflowException se estoque insuficiente
     */
    public function saida(
        Produto  $produto,
        float    $quantidade,
        string   $data,
        string   $origemTipo = 'manual',
        ?int     $origemId = null,
        ?int     $localId = null,
        ?int     $loteId = null,
        ?string  $observacao = null,
    ): MovimentacaoEstoque {
        if ($quantidade <= 0) {
            throw new \InvalidArgumentException('Quantidade de saída deve ser maior que zero.');
        }

        if ($produto->controla_estoque && $produto->estoque_atual < $quantidade) {
            throw new \UnderflowException(sprintf(
                "Estoque insuficiente para '%s'. Disponível: %.4f | Solicitado: %.4f",
                $produto->descricao,
                $produto->estoque_atual,
                $quantidade
            ));
        }

        return DB::transaction(function () use (
            $produto, $quantidade, $data, $origemTipo, $origemId, $localId, $loteId, $observacao
        ) {
            $saldoAnterior = $produto->estoque_atual;

            $produto->decrement('estoque_atual', $quantidade);

            if ($loteId) {
                Lote::where('id', $loteId)->decrement('quantidade_atual', $quantidade);
            }

            $mov = MovimentacaoEstoque::create([
                'empresa_id'        => $produto->empresa_id ?? tenant('id'),
                'produto_id'        => $produto->id,
                'local_estoque_id'  => $localId,
                'lote_id'           => $loteId,
                'tipo'              => 'saida',
                'origem_tipo'       => $origemTipo,
                'origem_id'         => $origemId,
                'quantidade'        => $quantidade,
                'custo_unitario'    => $produto->preco_custo, // CMP atual
                'saldo_anterior'    => $saldoAnterior,
                'saldo_posterior'   => $produto->fresh()->estoque_atual,
                'data_movimento'    => $data,
                'observacao'        => $observacao,
            ]);

            Log::info('Saída de estoque', [
                'produto_id' => $produto->id,
                'quantidade' => $quantidade,
            ]);

            return $mov;
        });
    }

    /**
     * Transferência entre locais de estoque.
     */
    public function transferir(
        Produto      $produto,
        LocalEstoque $origem,
        LocalEstoque $destino,
        float        $quantidade,
        string       $data,
        ?string      $observacao = null,
    ): array {
        if ($origem->id === $destino->id) {
            throw new \InvalidArgumentException('Local de origem e destino devem ser diferentes.');
        }

        return DB::transaction(function () use ($produto, $origem, $destino, $quantidade, $data, $observacao) {
            $saida = $this->saida($produto, $quantidade, $data, 'transferencia', null, $origem->id, null, $observacao);
            $entrada = $this->entrada($produto->fresh(), $quantidade, $produto->preco_custo, $data, 'transferencia', null, $destino->id, null, $observacao);

            return compact('saida', 'entrada');
        });
    }

    /**
     * Ajuste manual de estoque (acerto de diferença).
     */
    public function ajustar(
        Produto $produto,
        float   $quantidadeNova,
        string  $data,
        ?string $observacao = null,
    ): MovimentacaoEstoque {
        $diferenca = $quantidadeNova - $produto->estoque_atual;

        if (abs($diferenca) < 0.0001) {
            throw new \InvalidArgumentException('Quantidade informada é igual ao estoque atual. Nenhum ajuste necessário.');
        }

        if ($diferenca > 0) {
            return $this->entrada($produto, $diferenca, $produto->preco_custo, $data, 'ajuste', null, null, null, $observacao);
        }

        return $this->saida($produto, abs($diferenca), $data, 'ajuste', null, null, null, $observacao);
    }

    // ─── Recebimento de pedido de compra ──────────────────────────────────────

    /**
     * Registra recebimento (total ou parcial) de um pedido de compra.
     * Gera entradas de estoque para cada item recebido.
     *
     * @param array $itensRecebidos [['item_id' => int, 'quantidade' => float, 'custo' => float]]
     */
    public function receberPedidoCompra(
        PedidoCompra $pedido,
        array        $itensRecebidos,
        string       $data,
        ?int         $localId = null,
    ): PedidoCompra {
        return DB::transaction(function () use ($pedido, $itensRecebidos, $data, $localId) {
            foreach ($itensRecebidos as $recebido) {
                $item = PedidoCompraItem::findOrFail($recebido['item_id']);
                $qtdReceber = (float) $recebido['quantidade'];
                $custo = (float) ($recebido['custo'] ?? $item->preco_unitario);

                $pendente = $item->quantidade - $item->quantidade_recebida;
                if ($qtdReceber > $pendente + 0.0001) {
                    throw new \OverflowException(
                        "Item #{$item->numero_item}: quantidade a receber ({$qtdReceber}) "
                        . "excede o saldo pendente ({$pendente})."
                    );
                }

                $produto = $item->produto;
                $this->entrada(
                    produto:     $produto,
                    quantidade:  $qtdReceber,
                    custoUnitario: $custo,
                    data:        $data,
                    origemTipo:  'pedido_compra',
                    origemId:    $pedido->id,
                    localId:     $localId,
                );

                $item->increment('quantidade_recebida', $qtdReceber);
            }

            // Recalcula status do pedido
            $pedido->load('itens');
            $totalQtd     = $pedido->itens->sum('quantidade');
            $totalRecebido = $pedido->itens->sum('quantidade_recebida');

            $novoStatus = match(true) {
                $totalRecebido <= 0                     => 'confirmado',
                $totalRecebido < $totalQtd - 0.0001     => 'parcial',
                default                                 => 'recebido',
            };

            $pedido->update([
                'status'             => $novoStatus,
                'data_recebimento'   => $novoStatus === 'recebido' ? $data : $pedido->data_recebimento,
            ]);

            Log::info('Pedido de compra recebido', [
                'pedido_id' => $pedido->id,
                'status'    => $novoStatus,
            ]);

            return $pedido->fresh();
        });
    }

    // ─── Inventário ───────────────────────────────────────────────────────────

    /**
     * Finaliza inventário aplicando ajustes automáticos para as diferenças.
     */
    public function finalizarInventario(\App\Models\Inventario $inventario): \App\Models\Inventario
    {
        if ($inventario->status !== 'revisao') {
            throw new \InvalidArgumentException(
                "Inventário deve estar em status 'revisao' para ser finalizado. Status atual: {$inventario->status}"
            );
        }

        $itensNaoContados = $inventario->itens->where('contado', false)->count();
        if ($itensNaoContados > 0) {
            throw new \InvalidArgumentException(
                "{$itensNaoContados} item(ns) ainda não foram contados. Conclua a contagem antes de finalizar."
            );
        }

        return DB::transaction(function () use ($inventario) {
            foreach ($inventario->itens as $item) {
                if (abs($item->diferenca) < 0.0001) continue;

                $produto = $item->produto;
                $this->ajustar(
                    produto:        $produto,
                    quantidadeNova: $item->quantidade_contada,
                    data:           $inventario->data_inventario,
                    observacao:     "Ajuste de inventário #{$inventario->id}",
                );
            }

            $inventario->update([
                'status'         => 'finalizado',
                'finalizado_em'  => now(),
            ]);

            return $inventario->fresh();
        });
    }

    // ─── Consultas ────────────────────────────────────────────────────────────

    /**
     * Retorna posição de estoque atual com custo médio e valor total.
     */
    public function posicaoEstoque(int $empresaId, ?int $localId = null): Collection
    {
        return Produto::where('controla_estoque', true)
            ->where('is_active', true)
            ->select([
                'id', 'descricao', 'codigo', 'unidade_medida_id',
                'estoque_atual', 'estoque_minimo', 'estoque_maximo',
                'preco_custo',
                DB::raw('estoque_atual * preco_custo as valor_total'),
                DB::raw('CASE WHEN estoque_atual <= estoque_minimo THEN 1 ELSE 0 END as abaixo_minimo'),
            ])
            ->orderBy('descricao')
            ->get();
    }

    /**
     * Retorna produtos abaixo do estoque mínimo (ponto de reposição).
     */
    public function alertasEstoqueMinimo(int $empresaId): Collection
    {
        return Produto::where('controla_estoque', true)
            ->where('is_active', true)
            ->whereColumn('estoque_atual', '<=', 'estoque_minimo')
            ->orderBy('descricao')
            ->get();
    }

    /**
     * Retorna lotes próximos ao vencimento.
     *
     * @param int $diasAlerta Alertar com N dias de antecedência
     */
    public function lotesVencendo(int $diasAlerta = 30): Collection
    {
        return Lote::where('is_active', true)
            ->where('quantidade_atual', '>', 0)
            ->whereNotNull('data_validade')
            ->where('data_validade', '<=', now()->addDays($diasAlerta)->toDateString())
            ->orderBy('data_validade')
            ->with('produto')
            ->get();
    }

    // ─── Custo Médio Ponderado ────────────────────────────────────────────────

    /**
     * Calcula novo custo médio ponderado após uma entrada.
     * Retorna o custo atual se não houver estoque anterior.
     */
    public function calcularCmpEntrada(
        float $estoqueAtual,
        float $custoAtual,
        float $quantidadeEntrada,
        float $custoEntrada,
    ): float {
        $totalAnterior = $estoqueAtual + $quantidadeEntrada;
        if ($totalAnterior <= 0) return $custoEntrada;

        return round(
            ($estoqueAtual * $custoAtual + $quantidadeEntrada * $custoEntrada) / $totalAnterior,
            4
        );
    }
}
