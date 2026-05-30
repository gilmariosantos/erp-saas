<?php
namespace App\Http\Controllers\Tenant\Estoque;

use App\Http\Controllers\Controller;
use App\Models\PedidoCompra;
use App\Services\Estoque\EstoqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PedidoCompraController extends Controller
{
    public function __construct(private readonly EstoqueService $service) {}

    public function index(Request $request)
    {
        $query = PedidoCompra::with(['fornecedor', 'itens.produto'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('data_pedido');
        return response()->json($query->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'fornecedor_id' => ['required', 'exists:pessoas,id'],
            'data_pedido'   => ['required', 'date'],
            'itens'         => ['required', 'array', 'min:1'],
            'itens.*.produto_id' => ['required', 'exists:produtos,id'],
            'itens.*.quantidade' => ['required', 'numeric', 'min:0.0001'],
            'itens.*.preco_unitario' => ['required', 'numeric', 'min:0'],
        ]);

        $pedido = PedidoCompra::create([
            ...$request->except('itens'),
            'empresa_id' => auth()->user()->empresaAtualId(),
            'status'     => 'rascunho',
        ]);

        foreach ($request->input('itens') as $i => $item) {
            $pedido->itens()->create([
                ...$item,
                'numero_item' => $i + 1,
                'total' => round($item['quantidade'] * $item['preco_unitario'], 2),
            ]);
        }

        $pedido->update(['total_pedido' => $pedido->itens->sum('total')]);
        return response()->json(['message' => 'Pedido criado.', 'data' => $pedido->load('itens')], 201);
    }

    public function show(PedidoCompra $pedidoCompra): JsonResponse
    {
        return response()->json($pedidoCompra->load(['fornecedor', 'itens.produto', 'localEstoque']));
    }

    public function receber(Request $request, PedidoCompra $pedidoCompra): JsonResponse
    {
        $request->validate([
            'itens'        => ['required', 'array'],
            'itens.*.item_id'    => ['required', 'exists:pedido_compra_itens,id'],
            'itens.*.quantidade' => ['required', 'numeric', 'min:0.0001'],
            'itens.*.custo'      => ['nullable', 'numeric'],
            'data'         => ['required', 'date'],
        ]);

        try {
            $pedido = $this->service->receberPedidoCompra(
                $pedidoCompra,
                $request->input('itens'),
                $request->string('data'),
                $request->integer('local_estoque_id') ?: null,
            );
            return response()->json(['message' => 'Recebimento registrado.', 'data' => $pedido]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
