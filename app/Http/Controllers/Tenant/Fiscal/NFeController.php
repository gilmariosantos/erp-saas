<?php

namespace App\Http\Controllers\Tenant\Fiscal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\NFeRequest;
use App\Http\Requests\Fiscal\CancelarNFeRequest;
use App\Http\Requests\Fiscal\CartaCorrecaoRequest;
use App\Http\Resources\Fiscal\NFeResource;
use App\Jobs\EmitirNfe;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Services\Fiscal\NFeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group NF-e
 * Controller de Nota Fiscal Eletrônica (modelo 55 e 65).
 */
class NFeController extends Controller
{
    public function __construct(private readonly NFeService $service) {}

    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $this->authorize('viewAny', Nfe::class);

        $query = Nfe::with(['empresa', 'destinatario'])
            ->when($request->status,    fn ($q) => $q->where('status', $request->status))
            ->when($request->modelo,    fn ($q) => $q->where('modelo', $request->modelo))
            ->when($request->numero,    fn ($q) => $q->where('numero', $request->numero))
            ->when($request->chave,     fn ($q) => $q->where('chave_acesso', $request->chave))
            ->when($request->data_inicio, fn ($q) => $q->whereDate('data_emissao', '>=', $request->data_inicio))
            ->when($request->data_fim,    fn ($q) => $q->whereDate('data_emissao', '<=', $request->data_fim))
            ->orderByDesc('data_emissao');

        return NFeResource::collection($query->paginate((int) $request->get('per_page', 25)));
    }

    public function store(NFeRequest $request): JsonResponse
    {
        $this->authorize('create', Nfe::class);

        $empresa = Empresa::findOrFail($request->integer('empresa_id'));
        $numero  = $empresa->numero_nfe + 1;
        $empresa->increment('numero_nfe');

        $nfe = Nfe::create([
            ...$request->validated(),
            'numero'     => str_pad($numero, 9, '0', STR_PAD_LEFT),
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'NF-e criada em rascunho.',
            'data'    => new NFeResource($nfe),
        ], 201);
    }

    public function show(Nfe $nfe): NFeResource
    {
        $this->authorize('view', $nfe);
        return new NFeResource($nfe->load(['empresa', 'itens', 'destinatario', 'cobrancas']));
    }

    public function update(NFeRequest $request, Nfe $nfe): JsonResponse
    {
        $this->authorize('update', $nfe);

        if (! $nfe->podeEmitir()) {
            return response()->json(['message' => 'NF-e não pode ser editada neste status.'], 422);
        }

        $nfe->update([...$request->validated(), 'updated_by' => auth()->id()]);

        return response()->json(['message' => 'NF-e atualizada.', 'data' => new NFeResource($nfe->fresh())]);
    }

    /**
     * Emite a NF-e na SEFAZ de forma assíncrona (via fila).
     */
    public function emitir(Nfe $nfe): JsonResponse
    {
        $this->authorize('emitir', $nfe);

        if (! $nfe->podeEmitir()) {
            return response()->json([
                'message' => "NF-e com status '{$nfe->status->label()}' não pode ser emitida.",
            ], 422);
        }

        // Verifica limite do plano antes de emitir (enforcement de billing).
        // Lança LimiteExcedidoException (HTTP 402) se a cota mensal foi atingida.
        $tipo = $nfe->modelo === '65' ? 'nfce' : 'nfe';
        app(\App\Services\Billing\UsageLimitService::class)->verificarLimite(tenant(), $tipo);

        // Dispara job assíncrono — não bloqueia a requisição
        EmitirNfe::dispatch($nfe)->onQueue('fiscal');

        return response()->json([
            'message' => 'NF-e enviada para processamento. Acompanhe o status.',
            'data'    => new NFeResource($nfe->fresh()),
        ]);
    }

    /**
     * Emissão síncrona (útil para NFC-e em PDV, onde o retorno é imediato).
     */
    public function emitirSync(Nfe $nfe): JsonResponse
    {
        $this->authorize('emitir', $nfe);

        if (! $nfe->podeEmitir()) {
            return response()->json(['message' => 'NF-e não pode ser emitida.'], 422);
        }

        try {
            $nfe = $this->service->emitir($nfe);
            return response()->json([
                'message' => $nfe->isAutorizada() ? 'NF-e autorizada com sucesso.' : 'NF-e processada.',
                'data'    => new NFeResource($nfe),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erro ao emitir: ' . $e->getMessage()], 422);
        }
    }

    /**
     * Cancela uma NF-e autorizada.
     */
    public function cancelar(CancelarNFeRequest $request, Nfe $nfe): JsonResponse
    {
        $this->authorize('cancelar', $nfe);

        try {
            $nfe = $this->service->cancelar($nfe, $request->string('justificativa'));
            return response()->json([
                'message' => 'NF-e cancelada com sucesso.',
                'data'    => new NFeResource($nfe),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Emite Carta de Correção Eletrônica (CC-e).
     */
    public function cartaCorrecao(CartaCorrecaoRequest $request, Nfe $nfe): JsonResponse
    {
        $this->authorize('cartaCorrecao', $nfe);

        try {
            $nfe = $this->service->cartaCorrecao($nfe, $request->string('descricao'));
            return response()->json([
                'message' => "CC-e nº {$nfe->cce_sequencia} emitida com sucesso.",
                'data'    => new NFeResource($nfe),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Consulta situação da NF-e na SEFAZ.
     */
    public function consultar(Nfe $nfe): JsonResponse
    {
        $this->authorize('view', $nfe);

        try {
            $situacao = $this->service->consultar($nfe);
            return response()->json(['data' => $situacao]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Download do XML autorizado (URL assinada com expiração de 15 min).
     */
    public function downloadXml(Nfe $nfe): JsonResponse
    {
        $this->authorize('view', $nfe);

        if (! $nfe->path_xml) {
            return response()->json(['message' => 'XML não disponível.'], 404);
        }

        $url = Storage::disk('s3')->temporaryUrl($nfe->path_xml, now()->addMinutes(15));
        return response()->json(['url' => $url, 'expires_in' => 900]);
    }

    /**
     * Download do DANFE em PDF (URL assinada).
     */
    public function downloadPdf(Nfe $nfe): JsonResponse
    {
        $this->authorize('view', $nfe);

        if (! $nfe->path_pdf) {
            return response()->json(['message' => 'PDF não disponível.'], 404);
        }

        $url = Storage::disk('s3')->temporaryUrl($nfe->path_pdf, now()->addMinutes(15));
        return response()->json(['url' => $url, 'expires_in' => 900]);
    }

    /**
     * Inutiliza faixa de numeração.
     */
    public function inutilizar(Request $request): JsonResponse
    {
        $this->authorize('inutilizar', Nfe::class);

        $request->validate([
            'empresa_id'   => ['required', 'exists:empresas,id'],
            'serie'        => ['required', 'string'],
            'numero_inicio'=> ['required', 'integer', 'min:1'],
            'numero_fim'   => ['required', 'integer', 'gte:numero_inicio'],
            'justificativa'=> ['required', 'string', 'min:15'],
            'modelo'       => ['sometimes', 'in:55,65'],
        ]);

        try {
            $empresa = Empresa::findOrFail($request->integer('empresa_id'));
            $retorno = $this->service->inutilizar(
                $empresa,
                $request->string('serie'),
                $request->integer('numero_inicio'),
                $request->integer('numero_fim'),
                $request->string('justificativa'),
                $request->string('modelo', '55'),
            );
            return response()->json(['message' => 'Inutilização registrada.', 'data' => $retorno]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
