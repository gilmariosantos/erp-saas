<?php

namespace App\Services;

use App\Models\ContaBancaria;
use App\Models\Lancamento;
use App\Models\Nfe;
use App\Models\Cte;
use App\Models\PedidoVenda;
use App\Models\Produto;
use App\Services\Estoque\EstoqueService;
use Illuminate\Support\Facades\DB;

/**
 * Serviço de Dashboard e KPIs.
 * Agrega dados de todos os módulos para o painel principal.
 */
class DashboardService
{
    public function __construct(
        private readonly EstoqueService $estoqueService,
    ) {}

    /**
     * Retorna todos os KPIs do dashboard para um período.
     */
    public function kpis(int $empresaId, string $dataInicio, string $dataFim): array
    {
        return [
            'financeiro'  => $this->kpisFinanceiro($empresaId, $dataInicio, $dataFim),
            'vendas'      => $this->kpisVendas($empresaId, $dataInicio, $dataFim),
            'estoque'     => $this->kpisEstoque($empresaId),
            'fiscal'      => $this->kpisFiscal($empresaId, $dataInicio, $dataFim),
            'saldos'      => $this->saldosContas($empresaId),
        ];
    }

    // ─── Financeiro ───────────────────────────────────────────────────────────

    public function kpisFinanceiro(int $empresaId, string $inicio, string $fim): array
    {
        $base = Lancamento::where('empresa_id', $empresaId);

        $receber = (clone $base)->where('tipo', 'receber');
        $pagar   = (clone $base)->where('tipo', 'pagar');

        // Recebimentos do período
        $totalRecebido = (clone $receber)
            ->where('status', 'pago')
            ->whereBetween('data_pagamento', [$inicio, $fim])
            ->sum('valor_pago');

        // Pagamentos do período
        $totalPago = (clone $pagar)
            ->where('status', 'pago')
            ->whereBetween('data_pagamento', [$inicio, $fim])
            ->sum('valor_pago');

        // A receber em aberto
        $aReceber = (clone $receber)
            ->whereIn('status', ['aberto', 'parcial', 'vencido'])
            ->sum(DB::raw('valor_original - valor_pago'));

        // A pagar em aberto
        $aPagar = (clone $pagar)
            ->whereIn('status', ['aberto', 'parcial', 'vencido'])
            ->sum(DB::raw('valor_original - valor_pago'));

        // Vencidos
        $receitasVencidas = (clone $receber)->vencidos()->sum(DB::raw('valor_original - valor_pago'));
        $despesasVencidas = (clone $pagar)->vencidos()->sum(DB::raw('valor_original - valor_pago'));

        return [
            'total_recebido'       => round((float) $totalRecebido, 2),
            'total_pago'           => round((float) $totalPago, 2),
            'resultado_periodo'    => round((float) $totalRecebido - (float) $totalPago, 2),
            'a_receber'            => round((float) $aReceber, 2),
            'a_pagar'              => round((float) $aPagar, 2),
            'saldo_previsto'       => round((float) $aReceber - (float) $aPagar, 2),
            'receitas_vencidas'    => round((float) $receitasVencidas, 2),
            'despesas_vencidas'    => round((float) $despesasVencidas, 2),
        ];
    }

    public function saldosContas(int $empresaId): array
    {
        $contas = ContaBancaria::where('empresa_id', $empresaId)
            ->where('is_active', true)
            ->where('exibir_dashboard', true)
            ->get(['id', 'nome', 'tipo', 'saldo_atual', 'banco_nome', 'cor']);

        return [
            'contas'         => $contas->toArray(),
            'saldo_total'    => round($contas->sum('saldo_atual'), 2),
        ];
    }

    // ─── Vendas ───────────────────────────────────────────────────────────────

    public function kpisVendas(int $empresaId, string $inicio, string $fim): array
    {
        $pedidos = PedidoVenda::where('empresa_id', $empresaId)
            ->whereIn('status', ['faturado', 'entregue'])
            ->whereBetween('data_pedido', [$inicio, $fim]);

        $total         = (clone $pedidos)->sum('total_pedido');
        $quantidade    = (clone $pedidos)->count();
        $ticketMedio   = $quantidade > 0 ? round($total / $quantidade, 2) : 0;
        $orcamentos    = PedidoVenda::where('empresa_id', $empresaId)
            ->where('tipo', 'orcamento')
            ->whereIn('status', ['rascunho', 'aguardando_aprovacao'])
            ->count();
        $pedidosPendentes = PedidoVenda::where('empresa_id', $empresaId)
            ->whereIn('status', ['aprovado', 'em_separacao'])
            ->count();

        // Top 5 produtos mais vendidos
        $topProdutos = DB::table('pedido_venda_itens as pvi')
            ->join('pedidos_venda as pv', 'pv.id', '=', 'pvi.pedido_venda_id')
            ->join('produtos as p', 'p.id', '=', 'pvi.produto_id')
            ->where('pv.empresa_id', $empresaId)
            ->whereIn('pv.status', ['faturado', 'entregue'])
            ->whereBetween('pv.data_pedido', [$inicio, $fim])
            ->groupBy('pvi.produto_id', 'p.descricao')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->select('p.descricao', DB::raw('SUM(pvi.quantidade) as total_qty'), DB::raw('SUM(pvi.total) as total_valor'))
            ->get();

        return [
            'total_faturado'   => round((float) $total, 2),
            'quantidade_pedidos'=> $quantidade,
            'ticket_medio'     => $ticketMedio,
            'orcamentos_abertos'=> $orcamentos,
            'pedidos_pendentes' => $pedidosPendentes,
            'top_produtos'     => $topProdutos,
        ];
    }

    // ─── Estoque ──────────────────────────────────────────────────────────────

    public function kpisEstoque(int $empresaId): array
    {
        $posicao      = $this->estoqueService->posicaoEstoque($empresaId);
        $abaixoMinimo = $posicao->where('abaixo_minimo', 1);
        $lotesVencendo= $this->estoqueService->lotesVencendo(30)->count();

        return [
            'total_produtos'      => $posicao->count(),
            'valor_total_estoque' => round($posicao->sum('valor_total'), 2),
            'produtos_abaixo_minimo' => $abaixoMinimo->count(),
            'lotes_vencendo_30d'  => $lotesVencendo,
            'alertas'             => $abaixoMinimo->take(5)->values(),
        ];
    }

    // ─── Fiscal ───────────────────────────────────────────────────────────────

    public function kpisFiscal(int $empresaId, string $inicio, string $fim): array
    {
        $nfes = Nfe::where('empresa_id', $empresaId)
            ->whereBetween(DB::raw('DATE(data_emissao)'), [$inicio, $fim]);

        $ctes = Cte::where('empresa_id', $empresaId)
            ->whereBetween(DB::raw('DATE(data_emissao)'), [$inicio, $fim]);

        return [
            'nfe' => [
                'total_emitidas'   => (clone $nfes)->where('status', 'autorizada')->count(),
                'total_canceladas' => (clone $nfes)->where('status', 'cancelada')->count(),
                'total_rejeitadas' => (clone $nfes)->where('status', 'rejeitada')->count(),
                'valor_total'      => round((float) (clone $nfes)->where('status', 'autorizada')->sum('total_nota'), 2),
                'pendentes'        => Nfe::where('empresa_id', $empresaId)->whereIn('status', ['pendente','processando'])->count(),
            ],
            'cte' => [
                'total_emitidos'   => (clone $ctes)->where('status', 'autorizada')->count(),
                'total_cancelados' => (clone $ctes)->where('status', 'cancelada')->count(),
                'valor_total'      => round((float) (clone $ctes)->where('status', 'autorizada')->sum('valor_total_servico'), 2),
                'pendentes'        => Cte::where('empresa_id', $empresaId)->whereIn('status', ['pendente','processando'])->count(),
            ],
        ];
    }

    /**
     * Relatório de DRE simplificado por período.
     */
    public function dre(int $empresaId, string $inicio, string $fim): array
    {
        $lancamentos = Lancamento::where('empresa_id', $empresaId)
            ->where('status', 'pago')
            ->whereBetween('data_pagamento', [$inicio, $fim])
            ->with('planoConta')
            ->get();

        $receitas  = $lancamentos->where('tipo', 'receber')
            ->groupBy(fn($l) => $l->planoConta?->nome ?? 'Sem categoria')
            ->map(fn($g) => round($g->sum('valor_pago'), 2));

        $despesas  = $lancamentos->where('tipo', 'pagar')
            ->groupBy(fn($l) => $l->planoConta?->nome ?? 'Sem categoria')
            ->map(fn($g) => round($g->sum('valor_pago'), 2));

        $totalReceitas = round($receitas->sum(), 2);
        $totalDespesas = round($despesas->sum(), 2);

        return [
            'periodo'          => ['inicio' => $inicio, 'fim' => $fim],
            'receitas'         => $receitas,
            'despesas'         => $despesas,
            'total_receitas'   => $totalReceitas,
            'total_despesas'   => $totalDespesas,
            'resultado_liquido' => round($totalReceitas - $totalDespesas, 2),
            'margem_liquida'    => $totalReceitas > 0
                ? round((($totalReceitas - $totalDespesas) / $totalReceitas) * 100, 2)
                : 0,
        ];
    }
}
