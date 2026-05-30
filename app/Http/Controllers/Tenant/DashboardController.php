<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'data_inicio' => ['sometimes', 'date'],
            'data_fim'    => ['sometimes', 'date'],
        ]);

        $inicio = $request->string('data_inicio', now()->startOfMonth()->toDateString());
        $fim    = $request->string('data_fim',    now()->endOfMonth()->toDateString());
        $empresaId = auth()->user()->empresaAtualId();

        return response()->json([
            'data'   => $this->service->kpis($empresaId, $inicio, $fim),
            'periodo'=> ['inicio' => $inicio, 'fim' => $fim],
        ]);
    }

    public function dre(Request $request): JsonResponse
    {
        $request->validate([
            'data_inicio' => ['required', 'date'],
            'data_fim'    => ['required', 'date', 'after_or_equal:data_inicio'],
        ]);

        return response()->json([
            'data' => $this->service->dre(
                auth()->user()->empresaAtualId(),
                $request->data_inicio,
                $request->data_fim,
            ),
        ]);
    }

    public function saldos(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->saldosContas(auth()->user()->empresaAtualId()),
        ]);
    }
}
