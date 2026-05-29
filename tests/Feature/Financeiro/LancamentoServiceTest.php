<?php

use App\Enums\LancamentoStatus;
use App\Models\ContaBancaria;
use App\Models\Lancamento;
use App\Models\LancamentoBaixa;
use App\Services\Financeiro\ExtratoService;
use App\Services\Financeiro\LancamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function criarConta(float $saldo = 1000.0): ContaBancaria
{
    return ContaBancaria::factory()->create(['saldo_atual' => $saldo]);
}

function criarLancamento(array $attrs = []): Lancamento
{
    return Lancamento::factory()->create(array_merge([
        'tipo'           => 'receber',
        'valor_original' => 500.00,
        'valor_pago'     => 0,
        'status'         => LancamentoStatus::ABERTO,
        'data_vencimento' => today()->addDays(5)->toDateString(),
    ], $attrs));
}

function makeService(): LancamentoService
{
    return new LancamentoService(new ExtratoService());
}

// ─── Criar lançamento ─────────────────────────────────────────────────────────

describe('LancamentoService::criar()', function () {

    it('cria lançamento com status aberto', function () {
        $service = makeService();
        $lancamento = $service->criar([
            'empresa_id'     => 1,
            'tipo'           => 'receber',
            'descricao'      => 'Venda #001',
            'valor_original' => 1000.00,
            'data_emissao'   => today()->toDateString(),
            'data_vencimento'=> today()->addDays(30)->toDateString(),
        ]);

        expect($lancamento->status)->toBe(LancamentoStatus::ABERTO)
            ->and($lancamento->parcela_numero)->toBe(1)
            ->and($lancamento->parcela_total)->toBe(1);
    });

});

// ─── Criar parcelado ──────────────────────────────────────────────────────────

describe('LancamentoService::criarParcelado()', function () {

    it('cria 3 parcelas com valores corretos', function () {
        $service = makeService();
        $dados = [
            'empresa_id'      => 1,
            'tipo'            => 'receber',
            'descricao'       => 'Venda parcelada',
            'valor_original'  => 1000.00,
            'data_emissao'    => today()->toDateString(),
            'data_vencimento' => today()->addDays(30)->toDateString(),
        ];

        $parcelas = $service->criarParcelado($dados, 3);

        expect($parcelas)->toHaveCount(3)
            ->and($parcelas->sum('valor_original'))->toBe(1000.00)
            ->and($parcelas->first()->parcela_numero)->toBe(1)
            ->and($parcelas->last()->parcela_total)->toBe(3);
    });

    it('distribui centavo de arredondamento na última parcela', function () {
        $service = makeService();
        $dados = [
            'empresa_id'      => 1,
            'tipo'            => 'receber',
            'descricao'       => 'Teste arredondamento',
            'valor_original'  => 100.00,
            'data_emissao'    => today()->toDateString(),
            'data_vencimento' => today()->addDays(30)->toDateString(),
        ];

        $parcelas = $service->criarParcelado($dados, 3);
        $total = $parcelas->sum('valor_original');

        expect(abs($total - 100.00))->toBeLessThan(0.01);
    });

    it('cada parcela tem UUID de grupo igual', function () {
        $service = makeService();
        $dados = [
            'empresa_id'      => 1,
            'tipo'            => 'pagar',
            'descricao'       => 'Fornecedor ABC',
            'valor_original'  => 600.00,
            'data_emissao'    => today()->toDateString(),
            'data_vencimento' => today()->addDays(30)->toDateString(),
        ];

        $parcelas = $service->criarParcelado($dados, 3);
        $grupos = $parcelas->pluck('grupo_parcelas')->unique();

        expect($grupos)->toHaveCount(1);
    });

    it('lança exceção se número de parcelas inválido', function () {
        $service = makeService();
        expect(fn () => $service->criarParcelado([], 1))
            ->toThrow(\InvalidArgumentException::class, 'entre 2 e 360');
    });

});

// ─── Baixar ───────────────────────────────────────────────────────────────────

describe('LancamentoService::baixar()', function () {

    it('baixa pagamento total e atualiza status para pago', function () {
        $conta = criarConta(0.0);
        $lancamento = criarLancamento(['tipo' => 'receber', 'valor_original' => 500.0]);
        $service = makeService();

        $baixa = $service->baixar($lancamento, $conta, 500.0, today()->toDateString());

        expect($lancamento->fresh()->status)->toBe(LancamentoStatus::PAGO)
            ->and($lancamento->fresh()->valor_pago)->toBe(500.0)
            ->and($baixa->valor_pago)->toBe(500.0);
    });

    it('baixa parcial mantém status como parcial', function () {
        $conta = criarConta(0.0);
        $lancamento = criarLancamento(['valor_original' => 500.0]);
        $service = makeService();

        $service->baixar($lancamento, $conta, 200.0, today()->toDateString());

        expect($lancamento->fresh()->status)->toBe(LancamentoStatus::PARCIAL)
            ->and($lancamento->fresh()->valor_pago)->toBe(200.0);
    });

    it('incrementa saldo da conta ao receber', function () {
        $conta = criarConta(100.0);
        $lancamento = criarLancamento(['tipo' => 'receber', 'valor_original' => 300.0]);
        $service = makeService();

        $service->baixar($lancamento, $conta, 300.0, today()->toDateString());

        expect($conta->fresh()->saldo_atual)->toBe(400.0);
    });

    it('decrementa saldo da conta ao pagar', function () {
        $conta = criarConta(500.0);
        $lancamento = criarLancamento(['tipo' => 'pagar', 'valor_original' => 200.0]);
        $service = makeService();

        $service->baixar($lancamento, $conta, 200.0, today()->toDateString());

        expect($conta->fresh()->saldo_atual)->toBe(300.0);
    });

    it('lança exceção se valor pago excede saldo aberto', function () {
        $conta = criarConta(9999.0);
        $lancamento = criarLancamento(['valor_original' => 100.0]);
        $service = makeService();

        expect(fn () => $service->baixar($lancamento, $conta, 200.0, today()->toDateString()))
            ->toThrow(\InvalidArgumentException::class, 'excede o saldo aberto');
    });

    it('não aceita baixa em lançamento já pago', function () {
        $conta = criarConta();
        $lancamento = criarLancamento([
            'valor_original' => 100.0,
            'status' => LancamentoStatus::PAGO,
        ]);
        $service = makeService();

        expect(fn () => $service->baixar($lancamento, $conta, 50.0, today()->toDateString()))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('suporta duas baixas parciais que somam o total', function () {
        $conta = criarConta(0.0);
        $lancamento = criarLancamento(['valor_original' => 600.0]);
        $service = makeService();

        $service->baixar($lancamento, $conta, 300.0, today()->toDateString());
        $service->baixar($lancamento->fresh(), $conta, 300.0, today()->toDateString());

        expect($lancamento->fresh()->status)->toBe(LancamentoStatus::PAGO);
    });

});

// ─── Encargos ─────────────────────────────────────────────────────────────────

describe('LancamentoService::calcularEncargos()', function () {

    it('retorna zero de encargos para pagamento no prazo', function () {
        $lancamento = criarLancamento([
            'valor_original'  => 1000.0,
            'data_vencimento' => today()->toDateString(),
        ]);
        $service = makeService();

        $encargos = $service->calcularEncargos($lancamento, today()->toDateString());

        expect($encargos['juros'])->toBe(0.0)
            ->and($encargos['multa'])->toBe(0.0)
            ->and($encargos['total'])->toBe(1000.0);
    });

    it('calcula multa de 2% e juros proporcionais por 30 dias de atraso', function () {
        $lancamento = criarLancamento([
            'valor_original'  => 1000.0,
            'data_vencimento' => today()->subDays(30)->toDateString(),
        ]);
        $service = makeService();

        $encargos = $service->calcularEncargos($lancamento, today()->toDateString());

        // Multa: 2% de 1000 = 20
        // Juros: 1%/mês * 1 mês * 1000 = 10
        expect($encargos['multa'])->toBe(20.0)
            ->and($encargos['juros'])->toBe(10.0)
            ->and($encargos['total'])->toBe(1030.0);
    });

});

// ─── Transferência ────────────────────────────────────────────────────────────

describe('LancamentoService::transferir()', function () {

    it('transfere saldo entre duas contas', function () {
        $origem = criarConta(1000.0);
        $destino = criarConta(0.0);
        $service = makeService();

        $service->transferir($origem, $destino, 400.0, today()->toDateString());

        expect($origem->fresh()->saldo_atual)->toBe(600.0)
            ->and($destino->fresh()->saldo_atual)->toBe(400.0);
    });

    it('lança exceção se saldo insuficiente', function () {
        $origem = criarConta(100.0);
        $destino = criarConta(0.0);
        $service = makeService();

        expect(fn () => $service->transferir($origem, $destino, 500.0, today()->toDateString()))
            ->toThrow(\UnderflowException::class, 'Saldo insuficiente');
    });

    it('lança exceção se origem e destino são a mesma conta', function () {
        $conta = criarConta(500.0);
        $service = makeService();

        expect(fn () => $service->transferir($conta, $conta, 100.0, today()->toDateString()))
            ->toThrow(\InvalidArgumentException::class, 'diferentes');
    });

    it('lança exceção se valor é zero ou negativo', function () {
        $origem = criarConta(500.0);
        $destino = criarConta(0.0);
        $service = makeService();

        expect(fn () => $service->transferir($origem, $destino, 0, today()->toDateString()))
            ->toThrow(\InvalidArgumentException::class, 'maior que zero');
    });

});

// ─── Fluxo de caixa ───────────────────────────────────────────────────────────

describe('LancamentoService::fluxoCaixa()', function () {

    it('retorna fluxo de caixa projetado com saldo acumulado', function () {
        $empresaId = 1;
        $service = makeService();

        Lancamento::factory()->create([
            'empresa_id'      => $empresaId,
            'tipo'            => 'receber',
            'valor_original'  => 1000.0,
            'valor_pago'      => 0,
            'status'          => LancamentoStatus::ABERTO,
            'data_vencimento' => today()->toDateString(),
        ]);

        Lancamento::factory()->create([
            'empresa_id'      => $empresaId,
            'tipo'            => 'pagar',
            'valor_original'  => 300.0,
            'valor_pago'      => 0,
            'status'          => LancamentoStatus::ABERTO,
            'data_vencimento' => today()->toDateString(),
        ]);

        $fluxo = $service->fluxoCaixa($empresaId, today()->toDateString(), today()->toDateString());

        expect($fluxo)->toHaveCount(1)
            ->and($fluxo->first()['entradas'])->toBe(1000.0)
            ->and($fluxo->first()['saidas'])->toBe(300.0)
            ->and($fluxo->first()['saldo'])->toBe(700.0);
    });

});
