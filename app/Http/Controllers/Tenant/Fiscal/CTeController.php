<?php
namespace App\Http\Controllers\Tenant\Fiscal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Fiscal\CTeResource;
use App\Jobs\EmitirCte;
use App\Models\Cte;
use App\Models\Empresa;
use App\Services\Fiscal\CTeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CTeController extends Controller
{
    public function __construct(private readonly CTeService $service) {}

    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $query = Cte::with(['empresa', 'remetente', 'destinatario'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->data_inicio, fn($q) => $q->whereDate('data_emissao', '>=', $request->data_inicio))
            ->when($request->data_fim,    fn($q) => $q->whereDate('data_emissao', '<=', $request->data_fim))
            ->orderByDesc('data_emissao');
        return CTeResource::collection($query->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['empresa_id' => ['required', 'exists:empresas,id']]);
        $empresa = Empresa::findOrFail($request->integer('empresa_id'));
        $empresa->increment('numero_cte');
        $cte = Cte::create([
            ...$request->all(),
            'numero' => str_pad($empresa->numero_cte, 9, '0', STR_PAD_LEFT),
            'created_by' => auth()->id(),
        ]);
        return response()->json(['message' => 'CT-e criado.', 'data' => new CTeResource($cte)], 201);
    }

    public function show(Cte $cte): CTeResource
    {
        return new CTeResource($cte->load(['empresa', 'remetente', 'destinatario', 'documentos']));
    }

    public function emitir(Cte $cte): JsonResponse
    {
        EmitirCte::dispatch($cte)->onQueue('fiscal');
        return response()->json(['message' => 'CT-e enviado para processamento.', 'data' => new CTeResource($cte->fresh())]);
    }

    public function cancelar(Request $request, Cte $cte): JsonResponse
    {
        $request->validate(['justificativa' => ['required', 'string', 'min:15']]);
        try {
            $cte = $this->service->cancelar($cte, $request->string('justificativa'));
            return response()->json(['message' => 'CT-e cancelado.', 'data' => new CTeResource($cte)]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function consultar(Cte $cte): JsonResponse
    {
        return response()->json(['data' => ['status' => $cte->status->value, 'chave' => $cte->chave_acesso]]);
    }

    public function downloadXml(Cte $cte): JsonResponse
    {
        if (!$cte->path_xml) return response()->json(['message' => 'XML não disponível.'], 404);
        $url = Storage::disk('s3')->temporaryUrl($cte->path_xml, now()->addMinutes(15));
        return response()->json(['url' => $url]);
    }

    public function downloadDacte(Cte $cte): JsonResponse
    {
        if (!$cte->path_pdf) return response()->json(['message' => 'PDF não disponível.'], 404);
        $url = Storage::disk('s3')->temporaryUrl($cte->path_pdf, now()->addMinutes(15));
        return response()->json(['url' => $url]);
    }

    public function gerarCiot(Request $request): JsonResponse
    {
        $request->validate([
            'empresa_id'         => ['required', 'exists:empresas,id'],
            'cpf_cnpj_contratado'=> ['required', 'string'],
            'valor_frete'        => ['required', 'numeric', 'min:0.01'],
            'valor_pedagio'      => ['required', 'numeric', 'min:0'],
            'placa_veiculo'      => ['required', 'string'],
            'uf_origem'          => ['required', 'string', 'size:2'],
            'uf_destino'         => ['required', 'string', 'size:2'],
        ]);
        try {
            $empresa = Empresa::findOrFail($request->integer('empresa_id'));
            $ciot = $this->service->gerarCiot(
                cpfCnpjContratado:   $request->string('cpf_cnpj_contratado'),
                cpfCnpjContratante:  preg_replace('/\D/', '', $empresa->cnpj ?? ''),
                valorFrete:          $request->float('valor_frete'),
                valorPedagio:        $request->float('valor_pedagio'),
                placaVeiculo:        $request->string('placa_veiculo'),
                ufOrigem:            $request->string('uf_origem'),
                ufDestino:           $request->string('uf_destino'),
                empresa:             $empresa,
            );
            return response()->json(['message' => 'CIOT gerado.', 'data' => $ciot]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function consultarCiot(Request $request, string $ciot): JsonResponse
    {
        $request->validate(['empresa_id' => ['required', 'exists:empresas,id']]);
        $empresa = Empresa::findOrFail($request->integer('empresa_id'));
        $resultado = $this->service->consultarCiot($ciot, $empresa);
        return response()->json(['data' => $resultado]);
    }
}
