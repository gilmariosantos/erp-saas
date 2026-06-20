<?php
namespace App\Http\Controllers\Tenant\Servicos;

use App\Http\Controllers\Controller;
use App\Models\StatusOs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Ordem de Serviço — Configuração
 * CRUD dos status (fluxo kanban) configuráveis por empresa.
 */
class StatusOsController extends Controller
{
    public function index(): JsonResponse
    {
        $status = StatusOs::where('empresa_id', auth()->user()->empresaAtualId())
            ->orderBy('ordem')
            ->get();
        return response()->json(['data' => $status]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('configuracoes.gerenciar');
        $dados = $request->validate([
            'nome'         => ['required', 'string', 'max:60'],
            'cor'          => ['nullable', 'string', 'max:7'],
            'ordem'        => ['nullable', 'integer'],
            'is_inicial'   => ['boolean'],
            'is_final'     => ['boolean'],
            'is_cancelado' => ['boolean'],
        ]);

        $empresaId = auth()->user()->empresaAtualId();

        // Garante apenas 1 status inicial
        if ($dados['is_inicial'] ?? false) {
            StatusOs::where('empresa_id', $empresaId)->update(['is_inicial' => false]);
        }

        $status = StatusOs::create([
            ...$dados,
            'empresa_id' => $empresaId,
            'cor'        => $dados['cor'] ?? '#3B82F6',
            'ordem'      => $dados['ordem'] ?? (StatusOs::where('empresa_id', $empresaId)->max('ordem') + 1),
        ]);

        return response()->json(['message' => 'Status criado.', 'data' => $status], 201);
    }

    public function update(Request $request, StatusOs $statusOs): JsonResponse
    {
        $this->authorize('configuracoes.gerenciar');

        if ($request->boolean('is_inicial')) {
            StatusOs::where('empresa_id', $statusOs->empresa_id)
                ->where('id', '!=', $statusOs->id)
                ->update(['is_inicial' => false]);
        }

        $statusOs->update($request->only(['nome', 'cor', 'ordem', 'is_inicial', 'is_final', 'is_cancelado']));
        return response()->json(['message' => 'Status atualizado.', 'data' => $statusOs->fresh()]);
    }

    public function destroy(StatusOs $statusOs): JsonResponse
    {
        $this->authorize('configuracoes.gerenciar');
        $statusOs->delete();
        return response()->json(['message' => 'Status removido.']);
    }

    /**
     * Reordena os status (drag-and-drop no kanban).
     */
    public function reordenar(Request $request): JsonResponse
    {
        $this->authorize('configuracoes.gerenciar');
        $request->validate(['ordem' => ['required', 'array']]);

        DB::transaction(function () use ($request) {
            foreach ($request->input('ordem') as $posicao => $id) {
                StatusOs::where('id', $id)
                    ->where('empresa_id', auth()->user()->empresaAtualId())
                    ->update(['ordem' => $posicao]);
            }
        });

        return response()->json(['message' => 'Ordem atualizada.']);
    }
}
