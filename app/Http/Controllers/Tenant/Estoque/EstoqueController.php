<?php
namespace App\Http\Controllers\Tenant\Estoque;

use App\Http\Controllers\Controller;
use App\Models\Produto;
use App\Services\Estoque\EstoqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EstoqueController extends Controller
{
    public function __construct(private readonly EstoqueService $service) {}

    public function posicao(Request $request): JsonResponse
    {
        $posicao = $this->service->posicaoEstoque(auth()->user()->empresaAtualId());
        return response()->json(['data' => $posicao, 'total_valor' => round($posicao->sum('valor_total'), 2)]);
    }

    public function alertas(): JsonResponse
    {
        $alertas = $this->service->alertasEstoqueMinimo(auth()->user()->empresaAtualId());
        return response()->json(['data' => $alertas, 'total' => $alertas->count()]);
    }

    public function lotesVencendo(Request $request): JsonResponse
    {
        $dias = $request->integer('dias', 30);
        $lotes = $this->service->lotesVencendo($dias);
        return response()->json(['data' => $lotes, 'total' => $lotes->count()]);
    }

    public function movimentar(Request $request): JsonResponse
    {
        $request->validate([
            'produto_id' => ['required', 'exists:produtos,id'],
            'tipo'       => ['required', 'in:entrada,saida,ajuste'],
            'quantidade' => ['required', 'numeric', 'min:0.0001'],
            'custo_unitario' => ['nullable', 'numeric', 'min:0'],
            'data'       => ['required', 'date'],
            'observacao' => ['nullable', 'string'],
        ]);

        $produto = Produto::findOrFail($request->integer('produto_id'));

        $mov = match($request->string('tipo')->toString()) {
            'entrada' => $this->service->entrada(
                $produto, $request->float('quantidade'),
                $request->float('custo_unitario', $produto->preco_custo),
                $request->string('data'), 'manual',
                observacao: $request->string('observacao'),
            ),
            'saida' => $this->service->saida(
                $produto, $request->float('quantidade'),
                $request->string('data'), 'manual',
                observacao: $request->string('observacao'),
            ),
            'ajuste' => $this->service->ajustar(
                $produto, $request->float('quantidade'),
                $request->string('data'), $request->string('observacao'),
            ),
        };

        return response()->json(['message' => 'Movimentação registrada.', 'data' => $mov]);
    }
}
