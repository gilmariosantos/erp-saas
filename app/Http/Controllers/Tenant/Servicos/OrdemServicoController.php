<?php
namespace App\Http\Controllers\Tenant\Servicos;

use App\Http\Controllers\Controller;
use App\Models\OrdemServico;
use App\Services\Servicos\OrdemServicoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Ordem de Serviço
 */
class OrdemServicoController extends Controller
{
    public function __construct(private readonly OrdemServicoService $service) {}

    public function index(Request $request)
    {
        $query = OrdemServico::with(['cliente', 'tecnico', 'status', 'equipamento'])
            ->when($request->situacao, fn($q) => $q->where('situacao', $request->situacao))
            ->when($request->cliente_id, fn($q) => $q->where('cliente_id', $request->cliente_id))
            ->when($request->tecnico_id, fn($q) => $q->where('tecnico_id', $request->tecnico_id))
            ->when($request->search, fn($q) => $q->where('numero', 'like', "%{$request->search}%")
                ->orWhereHas('cliente', fn($c) => $c->where('nome', 'like', "%{$request->search}%")))
            ->orderByDesc('data_abertura');
        return response()->json($query->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'cliente_id'  => ['required', 'exists:pessoas,id'],
            'descricao_problema' => ['nullable', 'string'],
            'itens'       => ['array'],
            'itens.*.tipo' => ['required_with:itens', 'in:servico,peca'],
            'itens.*.descricao' => ['required_with:itens', 'string'],
        ]);

        try {
            $os = $this->service->criar(
                [...$request->except('itens'), 'empresa_id' => auth()->user()->empresaAtualId()],
                $request->input('itens', [])
            );
            return response()->json(['message' => 'OS criada.', 'data' => $os], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(OrdemServico $ordemServico): JsonResponse
    {
        return response()->json(
            $ordemServico->load(['cliente', 'equipamento', 'itens.produto', 'tecnico', 'historico.user', 'nfse', 'lancamento'])
        );
    }

    public function aprovar(OrdemServico $ordemServico): JsonResponse
    {
        try {
            return response()->json(['message' => 'Orçamento aprovado.', 'data' => $this->service->aprovar($ordemServico)]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function finalizar(Request $request, OrdemServico $ordemServico): JsonResponse
    {
        try {
            $os = $this->service->finalizar($ordemServico, [
                'gerar_nfse'       => $request->boolean('gerar_nfse', true),
                'gerar_financeiro' => $request->boolean('gerar_financeiro', true),
            ]);
            return response()->json(['message' => 'OS finalizada.', 'data' => $os]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function cancelar(Request $request, OrdemServico $ordemServico): JsonResponse
    {
        try {
            $os = $this->service->cancelar($ordemServico, $request->string('motivo'));
            return response()->json(['message' => 'OS cancelada.', 'data' => $os]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
