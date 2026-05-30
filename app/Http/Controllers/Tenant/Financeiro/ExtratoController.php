<?php
namespace App\Http\Controllers\Tenant\Financeiro;

use App\Http\Controllers\Controller;
use App\Models\ContaBancaria;
use App\Services\Financeiro\ExtratoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExtratoController extends Controller
{
    public function __construct(private readonly ExtratoService $service) {}

    public function importarOfx(Request $request): JsonResponse
    {
        $request->validate(['arquivo' => ['required', 'file', 'mimes:ofx,xml,txt']]);

        $conteudo = file_get_contents($request->file('arquivo')->getRealPath());
        $transacoes = $this->service->importarOfx($conteudo);

        return response()->json([
            'message' => "{$transacoes->count()} transações importadas.",
            'data'    => $transacoes,
        ]);
    }

    public function conciliar(Request $request): JsonResponse
    {
        $request->validate([
            'conta_bancaria_id' => ['required', 'exists:contas_bancarias,id'],
            'transacoes'        => ['required', 'array'],
        ]);

        $resultado = $this->service->conciliar(
            collect($request->input('transacoes')),
            $request->integer('conta_bancaria_id')
        );

        return response()->json([
            'conciliados'     => $resultado['conciliados']->count(),
            'sugestoes'       => $resultado['sugestoes']->count(),
            'nao_encontrados' => $resultado['naoEncontrados']->count(),
            'data'            => $resultado,
        ]);
    }
}
