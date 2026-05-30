<?php
namespace App\Http\Controllers\Tenant\Vendas;

use App\Http\Controllers\Controller;
use App\Models\PedidoVenda;
use App\Services\Vendas\PedidoVendaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PedidoVendaController extends Controller
{
    public function __construct(private readonly PedidoVendaService $service) {}

    public function index(Request $request)
    {
        $query = PedidoVenda::with(['cliente', 'vendedor'])
            ->when($request->tipo,    fn($q) => $q->where('tipo', $request->tipo))
            ->when($request->status,  fn($q) => $q->where('status', $request->status))
            ->when($request->search,  fn($q) => $q->whereHas('cliente', fn($c) => $c->where('nome', 'like', "%{$request->search}%")))
            ->when($request->data_inicio, fn($q) => $q->where('data_pedido', '>=', $request->data_inicio))
            ->when($request->data_fim,    fn($q) => $q->where('data_pedido', '<=', $request->data_fim))
            ->orderByDesc('data_pedido');
        return response()->json($query->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tipo'        => ['required', 'in:orcamento,pedido'],
            'cliente_id'  => ['required', 'exists:pessoas,id'],
            'data_pedido' => ['required', 'date'],
            'itens'       => ['required', 'array', 'min:1'],
            'itens.*.produto_id'     => ['required', 'exists:produtos,id'],
            'itens.*.quantidade'     => ['required', 'numeric', 'min:0.0001'],
            'itens.*.preco_unitario' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $pedido = $this->service->criar(
                [...$request->except('itens'), 'empresa_id' => auth()->user()->empresaAtualId()],
                $request->input('itens')
            );
            return response()->json(['message' => 'Pedido criado.', 'data' => $pedido], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(PedidoVenda $pedidoVenda): JsonResponse
    {
        return response()->json($pedidoVenda->load(['cliente', 'itens.produto', 'vendedor', 'nfe']));
    }

    public function update(Request $request, PedidoVenda $pedidoVenda): JsonResponse
    {
        if ($pedidoVenda->status !== 'rascunho') {
            return response()->json(['message' => 'Pedido não pode ser editado neste status.'], 422);
        }

        try {
            if ($request->has('itens')) {
                $pedidoVenda = $this->service->atualizarItens($pedidoVenda, $request->input('itens'));
            }
            $pedidoVenda->update($request->except('itens'));
            return response()->json(['message' => 'Pedido atualizado.', 'data' => $pedidoVenda->fresh()]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function aprovar(PedidoVenda $pedidoVenda): JsonResponse
    {
        try {
            $pedido = $this->service->aprovar($pedidoVenda);
            return response()->json(['message' => 'Pedido aprovado.', 'data' => $pedido]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function faturar(Request $request, PedidoVenda $pedidoVenda): JsonResponse
    {
        try {
            $pedido = $this->service->faturar($pedidoVenda, $request->string('data'));
            return response()->json(['message' => 'Pedido faturado.', 'data' => $pedido]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function cancelar(Request $request, PedidoVenda $pedidoVenda): JsonResponse
    {
        try {
            $pedido = $this->service->cancelar($pedidoVenda, $request->string('motivo'));
            return response()->json(['message' => 'Pedido cancelado.', 'data' => $pedido]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function converterOrcamento(PedidoVenda $pedidoVenda): JsonResponse
    {
        try {
            $pedido = $this->service->converterOrcamento($pedidoVenda);
            return response()->json(['message' => 'Orçamento convertido em pedido.', 'data' => $pedido]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function resumo(Request $request): JsonResponse
    {
        $request->validate([
            'data_inicio' => ['required', 'date'],
            'data_fim'    => ['required', 'date', 'after_or_equal:data_inicio'],
        ]);

        $resumo = $this->service->resumoVendas(
            auth()->user()->empresaAtualId(),
            $request->data_inicio,
            $request->data_fim,
        );

        return response()->json(['data' => $resumo]);
    }
}
