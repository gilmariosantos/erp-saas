<?php

use App\Http\Controllers\Tenant\Financeiro\LancamentoController;
use App\Http\Controllers\Tenant\Financeiro\ExtratoController;
use App\Http\Controllers\Tenant\Fiscal\NFeController;
use App\Http\Controllers\Tenant\Fiscal\CTeController;
use App\Http\Controllers\Tenant\Cadastros\PessoaController;
use App\Http\Controllers\Tenant\Cadastros\ProdutoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — V1 (multi-tenant)
|--------------------------------------------------------------------------
| Todas as rotas requerem:
|   - Autenticação via Sanctum (Bearer token)
|   - Middleware de tenant (identifica empresa pelo subdomínio ou header)
|   - Rate limiting: 60 req/min por usuário autenticado
*/

Route::middleware(['auth:sanctum', 'tenant', 'throttle:api'])
    ->prefix('v1')
    ->group(function () {

        // ─── Cadastros ───────────────────────────────────────────────────
        Route::apiResource('pessoas', PessoaController::class);
        Route::apiResource('produtos', ProdutoController::class);

        // ─── Financeiro ──────────────────────────────────────────────────
        Route::prefix('financeiro')->group(function () {

            // Contas a pagar e receber
            Route::apiResource('lancamentos', LancamentoController::class);

            // Ações específicas de lançamento
            Route::post('lancamentos/{lancamento}/baixar',
                [LancamentoController::class, 'baixar']
            )->name('lancamentos.baixar');

            Route::delete('lancamentos/{lancamento}/baixas/{baixa}/estornar',
                [LancamentoController::class, 'estornarBaixa']
            )->name('lancamentos.estornarBaixa');

            Route::get('lancamentos/{lancamento}/calcular-encargos',
                [LancamentoController::class, 'calcularEncargos']
            )->name('lancamentos.calcularEncargos');

            // Fluxo de caixa projetado
            Route::get('fluxo-caixa',
                [LancamentoController::class, 'fluxoCaixa']
            )->name('financeiro.fluxoCaixa');

            // Transferências
            Route::post('transferencias',
                [LancamentoController::class, 'transferir']
            )->name('financeiro.transferir');

            // Extrato e conciliação OFX
            Route::post('extrato/importar-ofx',
                [ExtratoController::class, 'importarOfx']
            )->name('extrato.importarOfx');

            Route::post('extrato/conciliar',
                [ExtratoController::class, 'conciliar']
            )->name('extrato.conciliar');
        });

        // ─── Fiscal ──────────────────────────────────────────────────────
        Route::prefix('fiscal')->group(function () {

            // NF-e
            Route::apiResource('nfes', NFeController::class);
            Route::post('nfes/{nfe}/emitir',        [NFeController::class, 'emitir']);
            Route::post('nfes/{nfe}/cancelar',      [NFeController::class, 'cancelar']);
            Route::post('nfes/{nfe}/carta-correcao',[NFeController::class, 'cartaCorrecao']);
            Route::get('nfes/{nfe}/consultar',      [NFeController::class, 'consultar']);
            Route::get('nfes/{nfe}/xml',            [NFeController::class, 'downloadXml']);
            Route::get('nfes/{nfe}/pdf',            [NFeController::class, 'downloadPdf']);
            Route::post('nfes/inutilizar',          [NFeController::class, 'inutilizar']);

            // CT-e
            Route::apiResource('ctes', CTeController::class);
            Route::post('ctes/{cte}/emitir',        [CTeController::class, 'emitir']);
            Route::post('ctes/{cte}/cancelar',      [CTeController::class, 'cancelar']);
            Route::get('ctes/{cte}/consultar',      [CTeController::class, 'consultar']);
            Route::get('ctes/{cte}/xml',            [CTeController::class, 'downloadXml']);
            Route::get('ctes/{cte}/pdf',            [CTeController::class, 'downloadDacte']);

            // CIOT
            Route::post('ciot/gerar',               [CTeController::class, 'gerarCiot']);
            Route::get('ciot/{ciot}/consultar',     [CTeController::class, 'consultarCiot']);
        });

    });
