<?php

use App\Models\LocalEstoque;
use App\Models\Lote;
use App\Models\MovimentacaoEstoque;
use App\Models\PedidoCompra;
use App\Models\PedidoCompraItem;
use App\Models\Produto;
use App\Services\Estoque\EstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function criarProduto(array $attrs = []): Produto
{
    return Produto::factory()->create(array_merge([
        'controla_estoque' => true,
        'estoque_atual'    => 10.0,
        'estoque_minimo'   => 2.0,
        'preco_custo'      => 100.0,
        'is_active'        => true,
    ], $attrs));
}

function makeEstoqueService(): EstoqueService
{
    return new EstoqueService();
}

// ─── Entrada ─────────────────────────────────────────────────────────────────

describe('EstoqueService::entrada()', function () {

    it('aumenta estoque_atual do produto', function () {
        $produto = criarProduto(['estoque_atual' => 10.0]);
        $service = makeEstoqueService();

        $service->entrada($produto, 5.0, 100.0, today()->toDateString());

        expect($produto->fresh()->estoque_atual)->toBe(15.0);
    });

    it('cria movimentação do tipo entrada', function () {
        $produto = criarProduto(['estoque_atual' => 0.0]);
        $service = makeEstoqueService();

        $mov = $service->entrada($produto, 10.0, 50.0, today()->toDateString());

        expect($mov->tipo)->toBe('entrada')
            ->and($mov->quantidade)->toBe(10.0)
            ->and($mov->custo_unitario)->toBe(50.0);
    });

    it('recalcula custo médio ponderado corretamente', function () {
        // Estoque: 10 un @ R$100 = R$1.000
        // Entrada: 10 un @ R$120 = R$1.200
        // CMP novo = (1.000 + 1.200) / 20 = R$110
        $produto = criarProduto(['estoque_atual' => 10.0, 'preco_custo' => 100.0]);
        $service = makeEstoqueService();

        $service->entrada($produto, 10.0, 120.0, today()->toDateString());

        expect($produto->fresh()->preco_custo)->toBe(110.0);
    });

    it('define custo médio como custo da entrada quando estoque é zero', function () {
        $produto = criarProduto(['estoque_atual' => 0.0, 'preco_custo' => 0.0]);
        $service = makeEstoqueService();

        $service->entrada($produto, 5.0, 80.0, today()->toDateString());

        expect($produto->fresh()->preco_custo)->toBe(80.0);
    });

    it('registra saldo anterior e posterior na movimentação', function () {
        $produto = criarProduto(['estoque_atual' => 10.0]);
        $service = makeEstoqueService();

        $mov = $service->entrada($produto, 5.0, 100.0, today()->toDateString());

        expect($mov->saldo_anterior)->toBe(10.0)
            ->and($mov->saldo_posterior)->toBe(15.0);
    });

    it('lança exceção para quantidade zero ou negativa', function () {
        $produto = criarProduto();
        $service = makeEstoqueService();

        expect(fn () => $service->entrada($produto, 0, 100.0, today()->toDateString()))
            ->toThrow(\InvalidArgumentException::class, 'maior que zero');
        expect(fn () => $service->entrada($produto, -5, 100.0, today()->toDateString()))
            ->toThrow(\InvalidArgumentException::class, 'maior que zero');
    });

    it('lança exceção para custo negativo', function () {
        $produto = criarProduto();
        $service = makeEstoqueService();

        expect(fn () => $service->entrada($produto, 5.0, -10.0, today()->toDateString()))
            ->toThrow(\InvalidArgumentException::class, 'negativo');
    });

    it('atualiza quantidade do lote quando lote_id informado', function () {
        $produto = criarProduto(['estoque_atual' => 0.0]);
        $lote = Lote::factory()->for($produto)->create(['quantidade_atual' => 0.0]);
        $service = makeEstoqueService();

        $service->entrada($produto, 20.0, 50.0, today()->toDateString(), loteId: $lote->id);

        expect($lote->fresh()->quantidade_atual)->toBe(20.0);
    });

});

// ─── Saída ────────────────────────────────────────────────────────────────────

describe('EstoqueService::saida()', function () {

    it('diminui estoque_atual do produto', function () {
        $produto = criarProduto(['estoque_atual' => 10.0]);
        $service = makeEstoqueService();

        $service->saida($produto, 3.0, today()->toDateString());

        expect($produto->fresh()->estoque_atual)->toBe(7.0);
    });

    it('usa custo médio atual para valorização da saída', function () {
        $produto = criarProduto(['estoque_atual' => 10.0, 'preco_custo' => 75.0]);
        $service = makeEstoqueService();

        $mov = $service->saida($produto, 2.0, today()->toDateString());

        expect($mov->custo_unitario)->toBe(75.0);
    });

    it('lança exceção se estoque insuficiente', function () {
        $produto = criarProduto(['estoque_atual' => 5.0]);
        $service = makeEstoqueService();

        expect(fn () => $service->saida($produto, 10.0, today()->toDateString()))
            ->toThrow(\UnderflowException::class, 'Estoque insuficiente');
    });

    it('permite saída quando produto não controla estoque', function () {
        $produto = criarProduto(['estoque_atual' => 0.0, 'controla_estoque' => false]);
        $service = makeEstoqueService();

        $mov = $service->saida($produto, 99.0, today()->toDateString());

        expect($mov->tipo)->toBe('saida');
    });

});

// ─── Custo Médio Ponderado ────────────────────────────────────────────────────

describe('EstoqueService::calcularCmpEntrada()', function () {

    it('calcula CMP corretamente com dois lotes', function () {
        $service = makeEstoqueService();
        // 100 un @ R$10 + 50 un @ R$13 = R$1.000 + R$650 = R$1.650 / 150 un = R$11
        $cmp = $service->calcularCmpEntrada(100, 10.0, 50, 13.0);
        expect($cmp)->toBe(11.0);
    });

    it('retorna custo da entrada quando estoque atual é zero', function () {
        $service = makeEstoqueService();
        $cmp = $service->calcularCmpEntrada(0, 0, 10, 25.0);
        expect($cmp)->toBe(25.0);
    });

    it('arredonda para 4 casas decimais', function () {
        $service = makeEstoqueService();
        $cmp = $service->calcularCmpEntrada(3, 10.0, 7, 13.0);
        // (30 + 91) / 10 = 12,1
        expect($cmp)->toBe(12.1);
    });

});

// ─── Ajuste ───────────────────────────────────────────────────────────────────

describe('EstoqueService::ajustar()', function () {

    it('ajusta estoque para cima', function () {
        $produto = criarProduto(['estoque_atual' => 10.0]);
        $service = makeEstoqueService();

        $service->ajustar($produto, 15.0, today()->toDateString(), 'Ajuste manual');

        expect($produto->fresh()->estoque_atual)->toBe(15.0);
    });

    it('ajusta estoque para baixo', function () {
        $produto = criarProduto(['estoque_atual' => 10.0]);
        $service = makeEstoqueService();

        $service->ajustar($produto, 6.0, today()->toDateString());

        expect($produto->fresh()->estoque_atual)->toBe(6.0);
    });

    it('lança exceção se quantidade igual ao estoque atual', function () {
        $produto = criarProduto(['estoque_atual' => 10.0]);
        $service = makeEstoqueService();

        expect(fn () => $service->ajustar($produto, 10.0, today()->toDateString()))
            ->toThrow(\InvalidArgumentException::class, 'igual ao estoque atual');
    });

});

// ─── Pedido de Compra ─────────────────────────────────────────────────────────

describe('EstoqueService::receberPedidoCompra()', function () {

    it('dá entrada nos produtos ao receber pedido', function () {
        $produto = criarProduto(['estoque_atual' => 0.0]);
        $pedido = PedidoCompra::factory()->create(['status' => 'confirmado']);
        $item = PedidoCompraItem::factory()->for($pedido)->for($produto)->create([
            'quantidade' => 10.0,
            'quantidade_recebida' => 0.0,
            'preco_unitario' => 50.0,
        ]);
        $service = makeEstoqueService();

        $service->receberPedidoCompra($pedido, [
            ['item_id' => $item->id, 'quantidade' => 10.0, 'custo' => 50.0]
        ], today()->toDateString());

        expect($produto->fresh()->estoque_atual)->toBe(10.0)
            ->and($pedido->fresh()->status)->toBe('recebido');
    });

    it('status fica parcial quando recebe menos que o pedido', function () {
        $produto = criarProduto(['estoque_atual' => 0.0]);
        $pedido = PedidoCompra::factory()->create(['status' => 'confirmado']);
        $item = PedidoCompraItem::factory()->for($pedido)->for($produto)->create([
            'quantidade' => 10.0,
            'quantidade_recebida' => 0.0,
            'preco_unitario' => 50.0,
        ]);
        $service = makeEstoqueService();

        $service->receberPedidoCompra($pedido, [
            ['item_id' => $item->id, 'quantidade' => 4.0, 'custo' => 50.0]
        ], today()->toDateString());

        expect($pedido->fresh()->status)->toBe('parcial');
    });

    it('lança exceção se quantidade recebida excede pendente', function () {
        $produto = criarProduto();
        $pedido = PedidoCompra::factory()->create(['status' => 'confirmado']);
        $item = PedidoCompraItem::factory()->for($pedido)->for($produto)->create([
            'quantidade' => 5.0,
            'quantidade_recebida' => 3.0,
        ]);
        $service = makeEstoqueService();

        expect(fn () => $service->receberPedidoCompra($pedido, [
            ['item_id' => $item->id, 'quantidade' => 5.0, 'custo' => 10.0]
        ], today()->toDateString()))
            ->toThrow(\OverflowException::class, 'excede o saldo pendente');
    });

});

// ─── Alertas ─────────────────────────────────────────────────────────────────

describe('EstoqueService::alertasEstoqueMinimo()', function () {

    it('retorna apenas produtos abaixo do mínimo', function () {
        criarProduto(['estoque_atual' => 1.0,  'estoque_minimo' => 5.0]); // abaixo
        criarProduto(['estoque_atual' => 10.0, 'estoque_minimo' => 5.0]); // ok
        criarProduto(['estoque_atual' => 5.0,  'estoque_minimo' => 5.0]); // no limite — inclui

        $service = makeEstoqueService();
        $alertas = $service->alertasEstoqueMinimo(1);

        expect($alertas)->toHaveCount(2);
    });

});
