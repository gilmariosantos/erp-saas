<?php
namespace App\Http\Controllers\Tenant\Servicos;

use App\Http\Controllers\Controller;
use App\Models\TipoOs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Ordem de Serviço — Configuração
 * CRUD dos tipos de OS configuráveis por empresa.
 */
class TipoOsController extends Controller
{
    public function index(): JsonResponse
    {
        $tipos = TipoOs::where('empresa_id', auth()->user()->empresaAtualId())
            ->orderBy('nome')
            ->get();
        return response()->json(['data' => $tipos]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('configuracoes.gerenciar');
        $dados = $request->validate([
            'nome'              => ['required', 'string', 'max:80'],
            'prefixo'           => ['nullable', 'string', 'max:10'],
            'exige_equipamento' => ['boolean'],
        ]);

        $tipo = TipoOs::create([
            ...$dados,
            'empresa_id' => auth()->user()->empresaAtualId(),
            'is_active'  => true,
        ]);

        return response()->json(['message' => 'Tipo de OS criado.', 'data' => $tipo], 201);
    }

    public function update(Request $request, TipoOs $tipoOs): JsonResponse
    {
        $this->authorize('configuracoes.gerenciar');
        $tipoOs->update($request->only(['nome', 'prefixo', 'exige_equipamento', 'is_active']));
        return response()->json(['message' => 'Tipo atualizado.', 'data' => $tipoOs->fresh()]);
    }

    public function destroy(TipoOs $tipoOs): JsonResponse
    {
        $this->authorize('configuracoes.gerenciar');
        // Não exclui se houver OS usando este tipo — apenas desativa
        if ($tipoOs->ordensServico()->exists() ?? false) {
            $tipoOs->update(['is_active' => false]);
            return response()->json(['message' => 'Tipo desativado (há OS vinculadas).']);
        }
        $tipoOs->delete();
        return response()->json(['message' => 'Tipo removido.']);
    }
}
