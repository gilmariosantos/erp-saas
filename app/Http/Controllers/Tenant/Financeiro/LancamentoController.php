<?php

namespace App\Http\Controllers\Tenant\Financeiro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financeiro\BaixaRequest;
use App\Http\Requests\Financeiro\LancamentoRequest;
use App\Http\Requests\Financeiro\TransferenciaRequest;
use App\Http\Resources\Financeiro\LancamentoResource;
use App\Models\ContaBancaria;
use App\Models\Lancamento;
use App\Models\LancamentoBaixa;
use App\Services\Financeiro\LancamentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Financeiro
 *
 * Controller de Contas a Pagar e Receber.
 * Todos os endpoints requerem autenticação e contexto de tenant.
 */
class LancamentoController extends Controller
{
    public function __construct(
        private readonly LancamentoService $service
    ) {}

    /**
     * Lista lançamentos com filtros.
     *
     * @queryParam tipo string Filtrar por tipo: pagar|receber
     * @queryParam status string Filtrar por status: aberto|parcial|pago|vencido
     * @queryParam data_inicio date Vencimento a partir de (Y-m-d)
     * @queryParam data_fim date Vencimento até (Y-m-d)
     * @queryParam pessoa_id int ID da pessoa
     * @queryParam per_page int Itens por página (padrão 25, max 100)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Lancamento::class);

        $query = Lancamento::query()
            ->where('empresa_id', auth()->user()->empresaAtiva()->id)
            ->with(['pessoa', 'contaBancaria', 'planoConta', 'centroCusto', 'formaPagamento'])
            ->when($request->tipo, fn ($q) => $q->where('tipo', $request->tipo))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->pessoa_id, fn ($q) => $q->where('pessoa_id', $request->pessoa_id))
            ->when($request->data_inicio, fn ($q) => $q->where('data_vencimento', '>=', $request->data_inicio))
            ->when($request->data_fim, fn ($q) => $q->where('data_vencimento', '<=', $request->data_fim))
            ->when($request->search, fn ($q) => $q->where('descricao', 'like', "%{$request->search}%")
                ->orWhere('numero_documento', 'like', "%{$request->search}%"))
            ->orderBy('data_vencimento');

        $perPage = min((int) $request->get('per_page', 25), 100);

        return LancamentoResource::collection($query->paginate($perPage));
    }

    /**
     * Cria um lançamento avulso ou parcelado.
     */
    public function store(LancamentoRequest $request): JsonResponse
    {
        $this->authorize('create', Lancamento::class);

        $dados = $request->validated();
        $dados['empresa_id'] = auth()->user()->empresaAtiva()->id;
        $dados['created_by'] = auth()->id();

        if ($request->integer('parcelas', 1) > 1) {
            $lancamentos = $this->service->criarParcelado(
                $dados,
                $request->integer('parcelas'),
                $request->string('intervalo', '30d')
            );
            return response()->json([
                'message' => "Lançamento criado em {$lancamentos->count()} parcelas.",
                'data'    => LancamentoResource::collection($lancamentos),
            ], 201);
        }

        $lancamento = $this->service->criar($dados);

        return response()->json([
            'message' => 'Lançamento criado com sucesso.',
            'data'    => new LancamentoResource($lancamento),
        ], 201);
    }

    /**
     * Exibe um lançamento com suas baixas.
     */
    public function show(Lancamento $lancamento): LancamentoResource
    {
        $this->authorize('view', $lancamento);

        return new LancamentoResource(
            $lancamento->load(['pessoa', 'contaBancaria', 'baixas.contaBancaria', 'baixas.formaPagamento'])
        );
    }

    /**
     * Atualiza um lançamento (somente em aberto).
     */
    public function update(LancamentoRequest $request, Lancamento $lancamento): JsonResponse
    {
        $this->authorize('update', $lancamento);

        if ($lancamento->status->isFinal()) {
            return response()->json([
                'message' => "Lançamento {$lancamento->status->label()} não pode ser editado.",
            ], 422);
        }

        $lancamento->update([
            ...$request->validated(),
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Lançamento atualizado.',
            'data'    => new LancamentoResource($lancamento->fresh()),
        ]);
    }

    /**
     * Cancela um lançamento.
     */
    public function destroy(Lancamento $lancamento): JsonResponse
    {
        $this->authorize('delete', $lancamento);

        if ($lancamento->status->isFinal()) {
            return response()->json(['message' => 'Lançamento não pode ser cancelado.'], 422);
        }

        $lancamento->update(['status' => \App\Enums\LancamentoStatus::CANCELADO]);

        return response()->json(['message' => 'Lançamento cancelado.']);
    }

    /**
     * Registra baixa (pagamento/recebimento) de um lançamento.
     */
    public function baixar(BaixaRequest $request, Lancamento $lancamento): JsonResponse
    {
        $this->authorize('baixar', $lancamento);

        $conta = ContaBancaria::findOrFail($request->integer('conta_bancaria_id'));
        $dados = $request->validated();

        // Calcula encargos automaticamente se solicitado
        if ($request->boolean('calcular_encargos')) {
            $encargos = $this->service->calcularEncargos($lancamento, $dados['data_pagamento']);
            $dados['valor_juros']  = $encargos['juros'];
            $dados['valor_multa']  = $encargos['multa'];
        }

        $baixa = $this->service->baixar(
            lancamento:       $lancamento,
            conta:            $conta,
            valorPago:        $dados['valor_pago'],
            dataPagamento:    $dados['data_pagamento'],
            valorJuros:       $dados['valor_juros'] ?? 0,
            valorMulta:       $dados['valor_multa'] ?? 0,
            valorDesconto:    $dados['valor_desconto'] ?? 0,
            formaPagamentoId: $dados['forma_pagamento_id'] ?? null,
            observacao:       $dados['observacao'] ?? null,
        );

        return response()->json([
            'message' => 'Baixa registrada com sucesso.',
            'data'    => new LancamentoResource($lancamento->fresh()),
            'baixa'   => $baixa,
        ]);
    }

    /**
     * Estorna uma baixa.
     */
    public function estornarBaixa(LancamentoBaixa $baixa): JsonResponse
    {
        $this->authorize('estornarBaixa', $baixa->lancamento);

        $this->service->estornarBaixa($baixa);

        return response()->json(['message' => 'Baixa estornada com sucesso.']);
    }

    /**
     * Retorna fluxo de caixa projetado.
     *
     * @queryParam data_inicio date required
     * @queryParam data_fim    date required
     */
    public function fluxoCaixa(Request $request): JsonResponse
    {
        $request->validate([
            'data_inicio' => ['required', 'date'],
            'data_fim'    => ['required', 'date', 'after_or_equal:data_inicio'],
        ]);

        $empresaId = auth()->user()->empresaAtiva()->id;

        $fluxo = $this->service->fluxoCaixa(
            $empresaId,
            $request->data_inicio,
            $request->data_fim
        );

        return response()->json([
            'data'   => $fluxo,
            'totais' => [
                'total_entradas' => round($fluxo->sum('entradas'), 2),
                'total_saidas'   => round($fluxo->sum('saidas'), 2),
                'saldo_final'    => round($fluxo->last()['saldo'] ?? 0.0, 2),
            ],
        ]);
    }

    /**
     * Transferência entre contas.
     */
    public function transferir(TransferenciaRequest $request): JsonResponse
    {
        $this->authorize('create', ContaBancaria::class);

        $origem  = ContaBancaria::findOrFail($request->integer('conta_origem_id'));
        $destino = ContaBancaria::findOrFail($request->integer('conta_destino_id'));

        $transferencia = $this->service->transferir(
            origem:    $origem,
            destino:   $destino,
            valor:     $request->float('valor'),
            data:      $request->string('data'),
            descricao: $request->string('descricao'),
        );

        return response()->json([
            'message' => 'Transferência realizada com sucesso.',
            'data'    => $transferencia,
        ], 201);
    }

    /**
     * Preview de encargos por atraso antes da baixa.
     */
    public function calcularEncargos(Request $request, Lancamento $lancamento): JsonResponse
    {
        $request->validate(['data_pagamento' => ['required', 'date']]);

        $encargos = $this->service->calcularEncargos(
            $lancamento,
            $request->data_pagamento
        );

        return response()->json(['data' => $encargos]);
    }
}
