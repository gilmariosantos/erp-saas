<?php

use App\Models\ContaBancaria;
use App\Models\Lancamento;
use App\Models\Nfe;
use App\Models\Produto;
use App\Enums\LancamentoStatus;
use App\Enums\NFeStatus;
use App\Services\DashboardService;
use App\Services\Estoque\EstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeDashboard(): DashboardService
{
    return new DashboardService(new EstoqueService());
}

// ─── KPIs Financeiro ─────────────────────────────────────────────────────────

describe('DashboardService::kpisFinanceiro()', function () {

    it('calcula total recebido no período', function () {
        Lancamento::factory()->create([
            'empresa_id'     => 1,
            'tipo'           => 'receber',
            'status'         => LancamentoStatus::PAGO,
            'valor_original' => 1000.0,
            'valor_pago'     => 1000.0,
            'data_pagamento' => today()->toDateString(),
        ]);

        $service = makeDashboard();
        $kpis = $service->kpisFinanceiro(1, today()->toDateString(), today()->toDateString());

        expect($kpis['total_recebido'])->toBe(1000.0);
    });

    it('calcula resultado do período (recebido - pago)', function () {
        Lancamento::factory()->create([
            'empresa_id' => 1, 'tipo' => 'receber',
            'status' => LancamentoStatus::PAGO, 'valor_pago' => 3000.0, 'data_pagamento' => today()->toDateString(),
        ]);
        Lancamento::factory()->create([
            'empresa_id' => 1, 'tipo' => 'pagar',
            'status' => LancamentoStatus::PAGO, 'valor_pago' => 1000.0, 'data_pagamento' => today()->toDateString(),
        ]);

        $kpis = makeDashboard()->kpisFinanceiro(1, today()->toDateString(), today()->toDateString());

        expect($kpis['resultado_periodo'])->toBe(2000.0);
    });

    it('calcula total a receber em aberto', function () {
        Lancamento::factory()->create([
            'empresa_id' => 1, 'tipo' => 'receber',
            'status' => LancamentoStatus::ABERTO,
            'valor_original' => 500.0, 'valor_pago' => 0,
        ]);

        $kpis = makeDashboard()->kpisFinanceiro(1, today()->toDateString(), today()->toDateString());

        expect($kpis['a_receber'])->toBe(500.0);
    });

    it('retorna zeros quando não há lançamentos', function () {
        $kpis = makeDashboard()->kpisFinanceiro(99, today()->toDateString(), today()->toDateString());

        expect($kpis['total_recebido'])->toBe(0.0)
            ->and($kpis['total_pago'])->toBe(0.0)
            ->and($kpis['resultado_periodo'])->toBe(0.0);
    });

});

// ─── Saldos de contas ─────────────────────────────────────────────────────────

describe('DashboardService::saldosContas()', function () {

    it('retorna saldo total consolidado', function () {
        ContaBancaria::factory()->create(['empresa_id' => 1, 'saldo_atual' => 2000.0, 'exibir_dashboard' => true]);
        ContaBancaria::factory()->create(['empresa_id' => 1, 'saldo_atual' => 1500.0, 'exibir_dashboard' => true]);

        $resultado = makeDashboard()->saldosContas(1);

        expect($resultado['saldo_total'])->toBe(3500.0)
            ->and($resultado['contas'])->toHaveCount(2);
    });

    it('exclui contas com exibir_dashboard=false', function () {
        ContaBancaria::factory()->create(['empresa_id' => 1, 'saldo_atual' => 500.0, 'exibir_dashboard' => false]);
        ContaBancaria::factory()->create(['empresa_id' => 1, 'saldo_atual' => 1000.0, 'exibir_dashboard' => true]);

        $resultado = makeDashboard()->saldosContas(1);

        expect($resultado['saldo_total'])->toBe(1000.0)
            ->and($resultado['contas'])->toHaveCount(1);
    });

});

// ─── KPIs Fiscal ─────────────────────────────────────────────────────────────

describe('DashboardService::kpisFiscal()', function () {

    it('conta NF-es autorizadas no período', function () {
        Nfe::factory()->autorizada()->create([
            'empresa_id'  => 1,
            'data_emissao'=> now(),
            'total_nota'  => 800.0,
        ]);
        Nfe::factory()->cancelada()->create([
            'empresa_id'  => 1,
            'data_emissao'=> now(),
        ]);

        $kpis = makeDashboard()->kpisFiscal(1, today()->toDateString(), today()->toDateString());

        expect($kpis['nfe']['total_emitidas'])->toBe(1)
            ->and($kpis['nfe']['total_canceladas'])->toBe(1)
            ->and($kpis['nfe']['valor_total'])->toBe(800.0);
    });

});

// ─── KPIs Estoque ─────────────────────────────────────────────────────────────

describe('DashboardService::kpisEstoque()', function () {

    it('conta produtos abaixo do mínimo', function () {
        Produto::factory()->create(['controla_estoque' => true, 'estoque_atual' => 1.0, 'estoque_minimo' => 5.0, 'preco_custo' => 10.0]);
        Produto::factory()->create(['controla_estoque' => true, 'estoque_atual' => 10.0, 'estoque_minimo' => 5.0, 'preco_custo' => 10.0]);

        $kpis = makeDashboard()->kpisEstoque(1);

        expect($kpis['produtos_abaixo_minimo'])->toBe(1);
    });

    it('calcula valor total do estoque', function () {
        Produto::factory()->create(['controla_estoque' => true, 'estoque_atual' => 10.0, 'preco_custo' => 50.0, 'estoque_minimo' => 0]);

        $kpis = makeDashboard()->kpisEstoque(1);

        expect($kpis['valor_total_estoque'])->toBe(500.0);
    });

});

// ─── DRE ─────────────────────────────────────────────────────────────────────

describe('DashboardService::dre()', function () {

    it('calcula resultado líquido corretamente', function () {
        Lancamento::factory()->create([
            'empresa_id'     => 1,
            'tipo'           => 'receber',
            'status'         => LancamentoStatus::PAGO,
            'valor_pago'     => 5000.0,
            'data_pagamento' => today()->toDateString(),
        ]);
        Lancamento::factory()->create([
            'empresa_id'     => 1,
            'tipo'           => 'pagar',
            'status'         => LancamentoStatus::PAGO,
            'valor_pago'     => 2000.0,
            'data_pagamento' => today()->toDateString(),
        ]);

        $dre = makeDashboard()->dre(1, today()->toDateString(), today()->toDateString());

        expect($dre['total_receitas'])->toBe(5000.0)
            ->and($dre['total_despesas'])->toBe(2000.0)
            ->and($dre['resultado_liquido'])->toBe(3000.0)
            ->and($dre['margem_liquida'])->toBe(60.0);
    });

    it('retorna margem zero quando não há receitas', function () {
        $dre = makeDashboard()->dre(99, today()->toDateString(), today()->toDateString());

        expect($dre['margem_liquida'])->toBe(0.0)
            ->and($dre['resultado_liquido'])->toBe(0.0);
    });

});
