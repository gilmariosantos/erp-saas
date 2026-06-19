<?php

namespace App\Services\Servicos;

use App\Models\Lancamento;
use App\Models\Nfse;
use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\Produto;
use App\Services\Estoque\EstoqueService;
use App\Services\Fiscal\NfseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de Ordem de Serviço.
 *
 * Orquestra o ciclo de vida completo da OS e suas integrações:
 *  - Estoque: baixa as peças ao finalizar
 *  - Fiscal: gera NFS-e dos serviços
 *  - Financeiro: cria lançamento a receber
 *
 * O fluxo respeita a configuração da empresa (tipos e status customizáveis).
 */
class OrdemServicoService
{
    public function __construct(
        private readonly EstoqueService $estoqueService,
        private readonly NfseService $nfseService,
    ) {}

    // ─── Criação ─────────────────────────────────────────────────────────────

    /**
     * Cria uma OS (inicialmente como orçamento).
     *
     * @param array $dados  Cabeçalho da OS
     * @param array $itens  Lista de itens (serviços e peças)
     */
    public function criar(array $dados, array $itens = []): OrdemServico
    {
        return DB::transaction(function () use ($dados, $itens) {
            $dados['numero']    = $this->gerarNumero($dados['empresa_id'], $dados['tipo_os_id'] ?? null);
            $dados['situacao']  = 'orcamento';
            $dados['data_abertura'] ??= today()->toDateString();

            $os = OrdemServico::create($dados);

            foreach ($itens as $i => $item) {
                $this->adicionarItem($os, $item, false);
            }

            $os->recalcularTotais();
            $this->registrarHistorico($os, 'OS criada', 'Ordem de serviço aberta como orçamento.');

            Log::info('OS criada', ['os_id' => $os->id, 'numero' => $os->numero]);

            return $os->fresh(['itens', 'cliente']);
        });
    }

    /**
     * Adiciona um item (serviço ou peça) à OS.
     */
    public function adicionarItem(OrdemServico $os, array $dados, bool $recalcular = true): OrdemServicoItem
    {
        $quantidade    = (float) ($dados['quantidade'] ?? 1);
        $valorUnitario = (float) ($dados['valor_unitario'] ?? 0);
        $desconto      = (float) ($dados['desconto_valor'] ?? 0);

        // Se for peça vinculada a produto, puxa o preço do cadastro
        if (($dados['tipo'] ?? null) === 'peca' && ! empty($dados['produto_id'])) {
            $produto = Produto::find($dados['produto_id']);
            if ($produto && $valorUnitario === 0.0) {
                $valorUnitario = (float) $produto->preco_venda;
            }
        }

        $total = round(($quantidade * $valorUnitario) - $desconto, 2);

        $item = OrdemServicoItem::create([
            'ordem_servico_id' => $os->id,
            'tipo'             => $dados['tipo'],
            'produto_id'       => $dados['produto_id'] ?? null,
            'descricao'        => $dados['descricao'],
            'quantidade'       => $quantidade,
            'valor_unitario'   => $valorUnitario,
            'desconto_valor'   => $desconto,
            'total'            => $total,
            'tecnico_id'       => $dados['tecnico_id'] ?? null,
            'aprovado'         => $dados['aprovado'] ?? true,
        ]);

        if ($recalcular) {
            $os->recalcularTotais();
        }

        return $item;
    }

    // ─── Fluxo de status ─────────────────────────────────────────────────────

    /**
     * Aprova o orçamento (cliente autorizou o serviço).
     */
    public function aprovar(OrdemServico $os): OrdemServico
    {
        if ($os->situacao !== 'orcamento') {
            throw new \InvalidArgumentException('Apenas orçamentos podem ser aprovados.');
        }

        $os->update([
            'situacao'               => 'aprovada',
            'data_aprovacao_cliente' => now(),
        ]);

        $this->registrarHistorico($os, 'Orçamento aprovado', 'Cliente aprovou o orçamento.');
        return $os->fresh();
    }

    /**
     * Finaliza a OS: baixa estoque das peças, gera NFS-e e lançamento financeiro.
     *
     * @param array $opcoes  ['gerar_nfse' => bool, 'gerar_financeiro' => bool]
     */
    public function finalizar(OrdemServico $os, array $opcoes = []): OrdemServico
    {
        if (! $os->podeFinalizar()) {
            throw new \InvalidArgumentException(
                "OS com situação '{$os->situacao}' não pode ser finalizada."
            );
        }

        $gerarNfse       = $opcoes['gerar_nfse'] ?? true;
        $gerarFinanceiro = $opcoes['gerar_financeiro'] ?? true;

        return DB::transaction(function () use ($os, $gerarNfse, $gerarFinanceiro) {
            $os->load(['itens.produto', 'cliente', 'empresa']);

            // 1. Baixa de estoque das peças (apenas as aprovadas)
            $this->baixarEstoquePecas($os);

            // 2. Gera NFS-e dos serviços (se houver serviços e solicitado)
            if ($gerarNfse && $os->servicos()->where('aprovado', true)->exists()) {
                $nfse = $this->gerarNfse($os);
                $os->nfse_id = $nfse->id;
            }

            // 3. Gera lançamento financeiro (a receber)
            if ($gerarFinanceiro && $os->total_geral > 0) {
                $lancamento = $this->gerarLancamentoFinanceiro($os);
                $os->lancamento_id = $lancamento->id;
            }

            // 4. Atualiza situação e garantia
            $os->situacao       = 'concluida';
            $os->data_conclusao = today()->toDateString();
            if ($os->garantia_dias > 0) {
                $os->garantia_ate = today()->addDays($os->garantia_dias)->toDateString();
            }
            $os->save();

            $this->registrarHistorico($os, 'OS finalizada',
                'Serviço concluído. ' .
                ($os->nfse_id ? 'NFS-e gerada. ' : '') .
                ($os->lancamento_id ? 'Lançamento financeiro criado.' : '')
            );

            Log::info('OS finalizada', [
                'os_id'          => $os->id,
                'nfse_id'        => $os->nfse_id,
                'lancamento_id'  => $os->lancamento_id,
                'total'          => $os->total_geral,
            ]);

            return $os->fresh(['itens', 'nfse', 'lancamento']);
        });
    }

    /**
     * Cancela a OS (e reverte estoque se já tinha baixado).
     */
    public function cancelar(OrdemServico $os, string $motivo = ''): OrdemServico
    {
        if (in_array($os->situacao, ['concluida', 'entregue', 'cancelada'])) {
            throw new \InvalidArgumentException(
                "OS com situação '{$os->situacao}' não pode ser cancelada."
            );
        }

        $os->update(['situacao' => 'cancelada']);
        $this->registrarHistorico($os, 'OS cancelada', $motivo ?: 'Ordem de serviço cancelada.');

        return $os->fresh();
    }

    // ─── Integrações privadas ──────────────────────────────────────────────────

    /**
     * Baixa do estoque todas as peças aprovadas da OS.
     */
    private function baixarEstoquePecas(OrdemServico $os): void
    {
        if ($os->estoque_baixado) return;

        $pecas = $os->itens()
            ->where('tipo', 'peca')
            ->where('aprovado', true)
            ->whereNotNull('produto_id')
            ->get();

        foreach ($pecas as $peca) {
            $produto = $peca->produto;
            if ($produto && $produto->controla_estoque) {
                $this->estoqueService->saida(
                    produto:    $produto,
                    quantidade: $peca->quantidade,
                    data:       today()->toDateString(),
                    origemTipo: 'ordem_servico',
                    origemId:   $os->id,
                    observacao: "Baixa pela OS #{$os->numero}",
                );
            }
        }

        $os->estoque_baixado = true;
        $os->save();
    }

    /**
     * Gera a NFS-e dos serviços da OS.
     */
    private function gerarNfse(OrdemServico $os): Nfse
    {
        $totalServicos = $os->servicos()->where('aprovado', true)->sum('total');

        $descricao = $os->servicos()->where('aprovado', true)->get()
            ->map(fn ($s) => $s->descricao)
            ->implode('; ');

        $nfse = Nfse::create([
            'empresa_id'        => $os->empresa_id,
            'tomador_id'        => $os->cliente_id,
            'tomador_nome'      => $os->cliente->nome,
            'tomador_cnpj_cpf'  => $os->cliente->documentoFormatado(),
            'data_emissao'      => now(),
            'data_competencia'  => now(),
            'descricao_servico' => "OS #{$os->numero}: {$descricao}",
            'valor_servico'     => $totalServicos,
            'valor_liquido'     => $totalServicos,
            'status'            => 'rascunho', // emissão real disparada à parte
            'padrao_municipal'  => $this->nfseService->detectarPadrao($os->empresa->codigo_municipio ?? ''),
            'codigo_municipio'  => $os->empresa->codigo_municipio,
        ]);

        return $nfse;
    }

    /**
     * Gera o lançamento financeiro (a receber) da OS.
     */
    private function gerarLancamentoFinanceiro(OrdemServico $os): Lancamento
    {
        return Lancamento::create([
            'empresa_id'      => $os->empresa_id,
            'tipo'            => 'receber',
            'descricao'       => "Recebimento OS #{$os->numero} — {$os->cliente->nome}",
            'pessoa_id'       => $os->cliente_id,
            'data_emissao'    => today()->toDateString(),
            'data_vencimento' => today()->toDateString(),
            'valor_original'  => $os->total_geral,
            'status'          => 'aberto',
            'origem_tipo'     => 'ordem_servico',
            'origem_id'       => $os->id,
            'parcela_numero'  => 1,
            'parcela_total'   => 1,
        ]);
    }

    // ─── Auxiliares ────────────────────────────────────────────────────────────

    private function registrarHistorico(OrdemServico $os, string $acao, string $descricao): void
    {
        $os->historico()->create([
            'user_id'   => auth()->id(),
            'status_id' => $os->status_id,
            'acao'      => $acao,
            'descricao' => $descricao,
        ]);
    }

    private function gerarNumero(int $empresaId, ?int $tipoOsId): string
    {
        $prefixo = 'OS';
        if ($tipoOsId) {
            $tipo = \App\Models\TipoOs::find($tipoOsId);
            $prefixo = $tipo?->prefixo ?: 'OS';
        }

        $ultimo = OrdemServico::where('empresa_id', $empresaId)
            ->whereYear('created_at', now()->year)
            ->count();

        return sprintf('%s-%d-%04d', $prefixo, now()->year, $ultimo + 1);
    }
}
