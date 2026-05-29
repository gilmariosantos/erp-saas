<?php

namespace App\Services\Vendas;

use App\Models\Comissao;
use App\Models\Nfe;
use App\Models\PedidoVenda;
use App\Models\PedidoVendaItem;
use App\Models\Produto;
use App\Models\RegrasComissao;
use App\Services\Estoque\EstoqueService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de Pedidos de Venda e Orçamentos.
 *
 * Responsável por:
 *  - Criar e aprovar pedidos/orçamentos
 *  - Reservar e dar saída no estoque ao faturar
 *  - Calcular totais, descontos e margens
 *  - Gerar comissões por vendedor
 *  - Converter orçamento em pedido
 *  - Integrar com NF-e (gerar NF a partir do pedido)
 */
class PedidoVendaService
{
    public function __construct(
        private readonly EstoqueService $estoqueService,
    ) {}

    // ─── Criação ─────────────────────────────────────────────────────────────

    /**
     * Cria um pedido ou orçamento com seus itens.
     *
     * @param array $dados   Cabeçalho do pedido
     * @param array $itens   Lista de itens: produto_id, quantidade, preco_unitario, desconto_percentual
     */
    public function criar(array $dados, array $itens): PedidoVenda
    {
        if (empty($itens)) {
            throw new \InvalidArgumentException('O pedido deve ter pelo menos um item.');
        }

        return DB::transaction(function () use ($dados, $itens) {
            $dados['status'] = 'rascunho';
            $dados['numero'] = $this->gerarNumero($dados['empresa_id']);
            $pedido = PedidoVenda::create($dados);

            $this->salvarItens($pedido, $itens);
            $pedido->recalcularTotais();

            Log::info('Pedido de venda criado', [
                'pedido_id' => $pedido->id,
                'tipo'      => $pedido->tipo,
                'total'     => $pedido->total_pedido,
            ]);

            return $pedido->fresh(['itens', 'cliente']);
        });
    }

    /**
     * Converte um orçamento em pedido de venda.
     */
    public function converterOrcamento(PedidoVenda $pedido): PedidoVenda
    {
        if ($pedido->tipo !== 'orcamento') {
            throw new \InvalidArgumentException('Apenas orçamentos podem ser convertidos em pedidos.');
        }

        if ($pedido->data_validade && $pedido->data_validade->isPast()) {
            throw new \InvalidArgumentException('Orçamento vencido não pode ser convertido.');
        }

        $pedido->update([
            'tipo'         => 'pedido',
            'status'       => 'aprovado',
            'data_pedido'  => today()->toDateString(),
        ]);

        Log::info('Orçamento convertido em pedido', ['pedido_id' => $pedido->id]);

        return $pedido->fresh();
    }

    /**
     * Aprova um pedido aguardando aprovação.
     */
    public function aprovar(PedidoVenda $pedido): PedidoVenda
    {
        if ($pedido->status !== 'aguardando_aprovacao') {
            throw new \InvalidArgumentException(
                "Pedido com status '{$pedido->status}' não pode ser aprovado."
            );
        }

        // Verifica disponibilidade de estoque para todos os itens
        $pedido->load('itens.produto');
        $semEstoque = $pedido->itens->filter(function ($item) {
            $prod = $item->produto;
            return $prod->controla_estoque && $prod->estoqueDisponivel() < $item->quantidade;
        });

        if ($semEstoque->isNotEmpty()) {
            $nomes = $semEstoque->map(fn ($i) => $i->produto->descricao)->implode(', ');
            throw new \UnderflowException("Estoque insuficiente para: {$nomes}");
        }

        return DB::transaction(function () use ($pedido) {
            // Reserva estoque
            foreach ($pedido->itens as $item) {
                $item->produto->increment('estoque_reservado', $item->quantidade);
            }

            $pedido->update(['status' => 'aprovado']);

            Log::info('Pedido aprovado e estoque reservado', ['pedido_id' => $pedido->id]);

            return $pedido->fresh();
        });
    }

    /**
     * Fatura o pedido: dá saída no estoque e gera comissões.
     * A NF-e é gerada em Job separado (EmitirNfe).
     */
    public function faturar(PedidoVenda $pedido, string $dataFaturamento = null): PedidoVenda
    {
        if (! in_array($pedido->status, ['aprovado', 'em_separacao'])) {
            throw new \InvalidArgumentException(
                "Pedido com status '{$pedido->status}' não pode ser faturado."
            );
        }

        $dataFaturamento ??= today()->toDateString();

        return DB::transaction(function () use ($pedido, $dataFaturamento) {
            $pedido->load('itens.produto');

            // Saída de estoque para cada item
            foreach ($pedido->itens as $item) {
                if ($item->produto->controla_estoque) {
                    $this->estoqueService->saida(
                        produto:   $item->produto,
                        quantidade: $item->quantidade,
                        data:      $dataFaturamento,
                        origemTipo:'pedido_venda',
                        origemId:  $pedido->id,
                    );
                    // Libera reserva
                    $item->produto->decrement('estoque_reservado', $item->quantidade);
                }
            }

            // Calcula e gera comissões
            $this->gerarComissoes($pedido);

            $pedido->update(['status' => 'faturado']);

            Log::info('Pedido faturado', [
                'pedido_id' => $pedido->id,
                'total'     => $pedido->total_pedido,
            ]);

            return $pedido->fresh();
        });
    }

    /**
     * Cancela um pedido e reverte reservas de estoque.
     */
    public function cancelar(PedidoVenda $pedido, string $motivo = ''): PedidoVenda
    {
        if (in_array($pedido->status, ['faturado', 'entregue', 'cancelado'])) {
            throw new \InvalidArgumentException(
                "Pedido com status '{$pedido->status}' não pode ser cancelado."
            );
        }

        return DB::transaction(function () use ($pedido) {
            // Libera reservas se estava aprovado
            if (in_array($pedido->status, ['aprovado', 'em_separacao'])) {
                foreach ($pedido->itens as $item) {
                    if ($item->produto->controla_estoque) {
                        $item->produto->decrement('estoque_reservado',
                            min($item->quantidade, $item->produto->estoque_reservado)
                        );
                    }
                }
            }

            $pedido->update(['status' => 'cancelado']);

            // Cancela comissões pendentes
            Comissao::where('pedido_venda_id', $pedido->id)
                ->where('status', 'pendente')
                ->update(['status' => 'cancelada']);

            return $pedido->fresh();
        });
    }

    // ─── Itens ────────────────────────────────────────────────────────────────

    /**
     * Adiciona ou atualiza itens do pedido (apenas em rascunho).
     */
    public function atualizarItens(PedidoVenda $pedido, array $itens): PedidoVenda
    {
        if ($pedido->status !== 'rascunho') {
            throw new \InvalidArgumentException('Itens só podem ser alterados em pedidos em rascunho.');
        }

        return DB::transaction(function () use ($pedido, $itens) {
            $pedido->itens()->delete();
            $this->salvarItens($pedido, $itens);
            $pedido->recalcularTotais();
            return $pedido->fresh(['itens']);
        });
    }

    // ─── Dashboard / Relatórios ───────────────────────────────────────────────

    /**
     * Retorna resumo de vendas por período.
     */
    public function resumoVendas(int $empresaId, string $dataInicio, string $dataFim): array
    {
        $pedidos = PedidoVenda::where('empresa_id', $empresaId)
            ->whereIn('status', ['faturado', 'entregue'])
            ->whereBetween('data_pedido', [$dataInicio, $dataFim])
            ->get();

        return [
            'total_pedidos'  => $pedidos->count(),
            'total_valor'    => round($pedidos->sum('total_pedido'), 2),
            'ticket_medio'   => $pedidos->count() > 0
                ? round($pedidos->avg('total_pedido'), 2)
                : 0,
            'por_vendedor'   => $pedidos->groupBy('vendedor_id')
                ->map(fn ($g) => [
                    'quantidade' => $g->count(),
                    'total'      => round($g->sum('total_pedido'), 2),
                ])->toArray(),
        ];
    }

    // ─── Privados ─────────────────────────────────────────────────────────────

    private function salvarItens(PedidoVenda $pedido, array $itens): void
    {
        foreach ($itens as $i => $itemDados) {
            $produto = Produto::findOrFail($itemDados['produto_id']);

            $quantidade      = (float) $itemDados['quantidade'];
            $precoUnitario   = (float) ($itemDados['preco_unitario'] ?? $produto->preco_venda);
            $descPct         = (float) ($itemDados['desconto_percentual'] ?? 0);
            $descValor       = round($precoUnitario * $quantidade * ($descPct / 100), 2);
            $total           = round(($precoUnitario * $quantidade) - $descValor, 2);
            $custo           = $produto->preco_custo;
            $margem          = $precoUnitario > 0
                ? round((($precoUnitario - $custo) / $precoUnitario) * 100, 4)
                : 0;

            PedidoVendaItem::create([
                'pedido_venda_id'    => $pedido->id,
                'produto_id'         => $produto->id,
                'numero_item'        => $i + 1,
                'descricao'          => $itemDados['descricao'] ?? $produto->descricao,
                'quantidade'         => $quantidade,
                'preco_unitario'     => $precoUnitario,
                'desconto_percentual'=> $descPct,
                'desconto_valor'     => $descValor,
                'total'              => $total,
                'custo_unitario'     => $custo,
                'margem'             => $margem,
            ]);
        }
    }

    private function gerarComissoes(PedidoVenda $pedido): void
    {
        if (! $pedido->vendedor_id) return;

        $regra = \App\Models\RegraComissao::where('empresa_id', $pedido->empresa_id)
            ->where(function ($q) use ($pedido) {
                $q->where('vendedor_id', $pedido->vendedor_id)
                  ->orWhereNull('vendedor_id');
            })
            ->where('is_active', true)
            ->orderBy('vendedor_id', 'desc') // regra específica tem prioridade
            ->first();

        if (! $regra) return;

        $base = $regra->base_calculo === 'valor_lucro'
            ? max(0, $pedido->total_pedido - $pedido->itens->sum(fn ($i) => $i->custo_unitario * $i->quantidade))
            : $pedido->total_pedido;

        if ($base < $regra->meta_minima) return;

        Comissao::create([
            'pedido_venda_id' => $pedido->id,
            'vendedor_id'     => $pedido->vendedor_id,
            'regra_id'        => $regra->id,
            'valor_base'      => $base,
            'percentual'      => $regra->percentual,
            'valor_comissao'  => round($base * ($regra->percentual / 100), 2),
            'status'          => 'pendente',
        ]);
    }

    private function gerarNumero(int $empresaId): string
    {
        $ultimo = PedidoVenda::where('empresa_id', $empresaId)
            ->whereYear('created_at', now()->year)
            ->max(DB::raw('CAST(numero AS UNSIGNED)'));

        return str_pad(($ultimo ?? 0) + 1, 6, '0', STR_PAD_LEFT);
    }
}
