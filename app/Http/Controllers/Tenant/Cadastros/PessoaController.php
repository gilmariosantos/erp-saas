<?php
namespace App\Http\Controllers\Tenant\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\Pessoa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PessoaController extends Controller
{
    public function index(Request $request)
    {
        $query = Pessoa::query()
            ->when($request->tipo, fn($q) => $q->where('tipo_pessoa', $request->tipo))
            ->when($request->boolean('cliente'), fn($q) => $q->where('is_cliente', true))
            ->when($request->boolean('fornecedor'), fn($q) => $q->where('is_fornecedor', true))
            ->when($request->boolean('transportadora'), fn($q) => $q->where('is_transportadora', true))
            ->when($request->search, fn($q) => $q->where('nome', 'like', "%{$request->search}%")
                ->orWhere('cnpj', 'like', "%{$request->search}%")
                ->orWhere('cpf', 'like', "%{$request->search}%"))
            ->where('is_active', true)
            ->orderBy('nome');
        return response()->json($query->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nome'        => ['required', 'string', 'max:150'],
            'tipo_pessoa' => ['required', 'in:PJ,PF'],
            'cnpj'        => ['nullable', 'string'],
            'cpf'         => ['nullable', 'string'],
            'email'       => ['nullable', 'email'],
        ]);
        $pessoa = Pessoa::create([...$request->all(), 'is_active' => true]);
        return response()->json(['message' => 'Pessoa cadastrada.', 'data' => $pessoa], 201);
    }

    public function show(Pessoa $pessoa) { return response()->json($pessoa); }

    public function update(Request $request, Pessoa $pessoa): JsonResponse
    {
        $pessoa->update($request->all());
        return response()->json(['message' => 'Atualizado.', 'data' => $pessoa->fresh()]);
    }

    public function destroy(Pessoa $pessoa): JsonResponse
    {
        $pessoa->delete();
        return response()->json(['message' => 'Removido.']);
    }
}
