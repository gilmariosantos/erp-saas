<?php

use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\Cadastros\PessoaController;
use App\Http\Controllers\Tenant\Cadastros\ProdutoController;
use App\Http\Controllers\Tenant\Estoque\EstoqueController;
use App\Http\Controllers\Tenant\Estoque\PedidoCompraController;
use App\Http\Controllers\Tenant\Financeiro\LancamentoController;
use App\Http\Controllers\Tenant\Financeiro\ExtratoController;
use App\Http\Controllers\Tenant\Fiscal\NFeController;
use App\Http\Controllers\Tenant\Fiscal\CTeController;
use App\Http\Controllers\Tenant\Vendas\PedidoVendaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])
    ->prefix('v1')
    ->group(function () {

        // Dashboard
        Route::get('dashboard',        [DashboardController::class, 'index']);
        Route::get('dashboard/dre',    [DashboardController::class, 'dre']);
        Route::get('dashboard/saldos', [DashboardController::class, 'saldos']);

        // Cadastros
        Route::apiResource('pessoas',  PessoaController::class);
        Route::apiResource('produtos', ProdutoController::class);

        // Financeiro
        Route::prefix('financeiro')->group(function () {
            Route::apiResource('lancamentos', LancamentoController::class);
            Route::post('lancamentos/{lancamento}/baixar',
                [LancamentoController::class, 'baixar']);
            Route::delete('lancamentos/{lancamento}/baixas/{baixa}/estornar',
                [LancamentoController::class, 'estornarBaixa']);
            Route::get('lancamentos/{lancamento}/calcular-encargos',
                [LancamentoController::class, 'calcularEncargos']);
            Route::get('fluxo-caixa',    [LancamentoController::class, 'fluxoCaixa']);
            Route::post('transferencias',[LancamentoController::class, 'transferir']);
            Route::post('extrato/importar-ofx', [ExtratoController::class, 'importarOfx']);
            Route::post('extrato/conciliar',    [ExtratoController::class, 'conciliar']);
        });

        // Estoque
        Route::prefix('estoque')->group(function () {
            Route::get('posicao',        [EstoqueController::class, 'posicao']);
            Route::get('alertas',        [EstoqueController::class, 'alertas']);
            Route::get('lotes-vencendo', [EstoqueController::class, 'lotesVencendo']);
            Route::post('movimentar',    [EstoqueController::class, 'movimentar']);
            Route::apiResource('pedidos-compra', PedidoCompraController::class);
            Route::post('pedidos-compra/{pedidoCompra}/receber',
                [PedidoCompraController::class, 'receber']);
        });

        // Vendas / CRM
        Route::prefix('vendas')->group(function () {
            Route::get('resumo', [PedidoVendaController::class, 'resumo']);
            Route::apiResource('pedidos', PedidoVendaController::class)
                ->parameters(['pedidos' => 'pedidoVenda']);
            Route::post('pedidos/{pedidoVenda}/aprovar',          [PedidoVendaController::class, 'aprovar']);
            Route::post('pedidos/{pedidoVenda}/faturar',          [PedidoVendaController::class, 'faturar']);
            Route::post('pedidos/{pedidoVenda}/cancelar',         [PedidoVendaController::class, 'cancelar']);
            Route::post('pedidos/{pedidoVenda}/converter-orcamento', [PedidoVendaController::class, 'converterOrcamento']);
        });

        // Fiscal
        Route::prefix('fiscal')->group(function () {
            // NF-e
            Route::apiResource('nfes', NFeController::class);
            Route::post('nfes/{nfe}/emitir',         [NFeController::class, 'emitir']);
            Route::post('nfes/{nfe}/emitir-sync',    [NFeController::class, 'emitirSync']);
            Route::post('nfes/{nfe}/cancelar',       [NFeController::class, 'cancelar']);
            Route::post('nfes/{nfe}/carta-correcao', [NFeController::class, 'cartaCorrecao']);
            Route::get('nfes/{nfe}/consultar',       [NFeController::class, 'consultar']);
            Route::get('nfes/{nfe}/xml',             [NFeController::class, 'downloadXml']);
            Route::get('nfes/{nfe}/pdf',             [NFeController::class, 'downloadPdf']);
            Route::post('nfes/inutilizar',           [NFeController::class, 'inutilizar']);
            // CT-e
            Route::apiResource('ctes', CTeController::class);
            Route::post('ctes/{cte}/emitir',    [CTeController::class, 'emitir']);
            Route::post('ctes/{cte}/cancelar',  [CTeController::class, 'cancelar']);
            Route::get('ctes/{cte}/consultar',  [CTeController::class, 'consultar']);
            Route::get('ctes/{cte}/xml',        [CTeController::class, 'downloadXml']);
            Route::get('ctes/{cte}/pdf',        [CTeController::class, 'downloadDacte']);
            Route::post('ciot/gerar',           [CTeController::class, 'gerarCiot']);
            Route::get('ciot/{ciot}/consultar', [CTeController::class, 'consultarCiot']);
        });
    });
