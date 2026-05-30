<?php
namespace App\Http\Controllers\Tenant\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\Produto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProdutoController extends Controller
{
    public function index(Request $request)
    {
        $query = Produto::with(['unidadeMedida', 'categoria'])
            ->when($request->tipo, fn($q) => $q->where('tipo', $request->tipo))
            ->when($request->search, fn($q) => $q->where('descricao', 'like', "%{$request->search}%")
                ->orWhere('codigo', 'like', "%{$request->search}%")
                ->orWhere('codigo_barras', 'like', "%{$request->search}%"))
            ->when($request->boolean('estoque_baixo'), fn($q) => $q->estoqueBaixo())
            ->ativos()->orderBy('descricao');
        return response()->json($query->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'descricao' => ['required', 'string', 'max:150'],
            'tipo'      => ['required', 'in:P,S,C'],
        ]);
        $produto = Produto::create([...$request->all(), 'is_active' => true]);
        return response()->json(['message' => 'Produto cadastrado.', 'data' => $produto], 201);
    }

    public function show(Produto $produto) { return response()->json($produto->load(['unidadeMedida','categoria','movimentacoes' => fn($q) => $q->latest()->limit(10)])); }

    public function update(Request $request, Produto $produto): JsonResponse
    {
        $produto->update($request->all());
        return response()->json(['message' => 'Atualizado.', 'data' => $produto->fresh()]);
    }

    public function destroy(Produto $produto): JsonResponse
    {
        $produto->update(['is_active' => false]);
        return response()->json(['message' => 'Produto desativado.']);
    }
}
