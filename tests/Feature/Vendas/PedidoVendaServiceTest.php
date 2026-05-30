<?php

use App\Models\Produto;
use App\Models\PedidoVenda;
use App\Models\Pessoa;
use App\Services\Estoque\EstoqueService;
use App\Services\Vendas\PedidoVendaService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeVendasService(): PedidoVendaService
{
    return new PedidoVendaService(new EstoqueService());
}

function dadosPedido(array $extra = []): array
{
    $cliente = Pessoa::factory()->cliente()->create();
    return array_merge([
        'empresa_id'  => 1,
        'tipo'        => 'pedido',
        'cliente_id'  => $cliente->id,
        'data_pedido' => today()->toDateString(),
    ], $extra);
}

function itemPedido(array $extra = []): array
{
    $produto = Produto::factory()->create([
        'preco_venda'   => 100.0,
        'preco_custo'   => 60.0,
        'estoque_atual' => 50.0,
    ]);
    return array_merge([
        'produto_id'          => $produto->id,
        'quantidade'          => 2.0,
        'preco_unitario'      => 100.0,
        'desconto_percentual' => 0,
    ], $extra);
}

// ─── Criar ────────────────────────────────────────────────────────────────────

describe('PedidoVendaService::criar()', function () {

    it('cria pedido com itens e calcula total corretamente', function () {
        $service = makeVendasService();
        $pedido = $service->criar(dadosPedido(), [itemPedido()]);

        expect($pedido->status)->toBe('rascunho')
            ->and($pedido->total_pedido)->toBe(200.0)
            ->and($pedido->itens)->toHaveCount(1);
    });

    it('gera número sequencial por empresa e ano', function () {
        $service = makeVendasService();
        $p1 = $service->criar(dadosPedido(), [itemPedido()]);
        $p2 = $service->criar(dadosPedido(), [itemPedido()]);

        expect((int) $p2->numero)->toBeGreaterThan((int) $p1->numero);
    });

    it('calcula desconto percentual corretamente', function () {
        $service = makeVendasService();
        $pedido = $service->criar(
            dadosPedido(),
            [itemPedido(['quantidade' => 1.0, 'preco_unitario' => 200.0, 'desconto_percentual' => 10])]
        );
        // 200 - 10% = 180
        expect($pedido->total_pedido)->toBe(180.0);
    });

    it('lança exceção se não tiver itens', function () {
        $service = makeVendasService();
        expect(fn () => $service->criar(dadosPedido(), []))
            ->toThrow(\InvalidArgumentException::class, 'pelo menos um item');
    });

    it('snapshot do custo unitário no momento da venda', function () {
        $service = makeVendasService();
        $pedido = $service->criar(dadosPedido(), [itemPedido()]);
        expect($pedido->itens->first()->custo_unitario)->toBe(60.0);
    });

});

// ─── Converter orçamento ──────────────────────────────────────────────────────

describe('PedidoVendaService::converterOrcamento()', function () {

    it('converte orçamento em pedido', function () {
        $service = makeVendasService();
        $orcamento = $service->criar(dadosPedido(['tipo' => 'orcamento']), [itemPedido()]);

        $pedido = $service->converterOrcamento($orcamento);

        expect($pedido->tipo)->toBe('pedido')
            ->and($pedido->status)->toBe('aprovado');
    });

    it('lança exceção ao converter pedido (não orçamento)', function () {
        $service = makeVendasService();
        $pedido = $service->criar(dadosPedido(), [itemPedido()]);

        expect(fn () => $service->converterOrcamento($pedido))
            ->toThrow(\InvalidArgumentException::class, 'Apenas orçamentos');
    });

    it('lança exceção para orçamento vencido', function () {
        $service = makeVendasService();
        $orcamento = PedidoVenda::factory()->create([
            'tipo'          => 'orcamento',
            'status'        => 'rascunho',
            'data_validade' => today()->subDay()->toDateString(),
        ]);

        expect(fn () => $service->converterOrcamento($orcamento))
            ->toThrow(\InvalidArgumentException::class, 'vencido');
    });

});

// ─── Aprovar ─────────────────────────────────────────────────────────────────

describe('PedidoVendaService::aprovar()', function () {

    it('aprova pedido e reserva estoque', function () {
        $service = makeVendasService();
        $produto = Produto::factory()->create(['estoque_atual' => 20.0, 'estoque_reservado' => 0]);
        $pedido = PedidoVenda::factory()->create(['status' => 'aguardando_aprovacao']);
        \App\Models\PedidoVendaItem::factory()->create([
            'pedido_venda_id' => $pedido->id,
            'produto_id'      => $produto->id,
            'quantidade'      => 5.0,
        ]);

        $service->aprovar($pedido);

        expect($produto->fresh()->estoque_reservado)->toBe(5.0)
            ->and($pedido->fresh()->status)->toBe('aprovado');
    });

    it('lança exceção para estoque insuficiente', function () {
        $service = makeVendasService();
        $produto = Produto::factory()->create([
            'controla_estoque' => true,
            'estoque_atual'    => 2.0,
            'estoque_reservado'=> 0,
        ]);
        $pedido = PedidoVenda::factory()->create(['status' => 'aguardando_aprovacao']);
        \App\Models\PedidoVendaItem::factory()->create([
            'pedido_venda_id' => $pedido->id,
            'produto_id'      => $produto->id,
            'quantidade'      => 10.0,
        ]);

        expect(fn () => $service->aprovar($pedido))
            ->toThrow(\UnderflowException::class, 'Estoque insuficiente');
    });

});

// ─── Faturar ──────────────────────────────────────────────────────────────────

describe('PedidoVendaService::faturar()', function () {

    it('fatura pedido e dá saída no estoque', function () {
        $service = makeVendasService();
        $produto = Produto::factory()->create([
            'estoque_atual'    => 20.0,
            'estoque_reservado'=> 5.0,
        ]);
        $pedido = PedidoVenda::factory()->create(['status' => 'aprovado']);
        \App\Models\PedidoVendaItem::factory()->create([
            'pedido_venda_id' => $pedido->id,
            'produto_id'      => $produto->id,
            'quantidade'      => 5.0,
        ]);

        $service->faturar($pedido);

        expect($produto->fresh()->estoque_atual)->toBe(15.0)
            ->and($produto->fresh()->estoque_reservado)->toBe(0.0)
            ->and($pedido->fresh()->status)->toBe('faturado');
    });

    it('não fatura pedido cancelado', function () {
        $service = makeVendasService();
        $pedido = PedidoVenda::factory()->create(['status' => 'cancelado']);

        expect(fn () => $service->faturar($pedido))
            ->toThrow(\InvalidArgumentException::class, 'não pode ser faturado');
    });

});

// ─── Cancelar ─────────────────────────────────────────────────────────────────

describe('PedidoVendaService::cancelar()', function () {

    it('cancela pedido aprovado e libera reserva', function () {
        $service = makeVendasService();
        $produto = Produto::factory()->create([
            'estoque_atual'    => 20.0,
            'estoque_reservado'=> 5.0,
        ]);
        $pedido = PedidoVenda::factory()->create(['status' => 'aprovado']);
        \App\Models\PedidoVendaItem::factory()->create([
            'pedido_venda_id' => $pedido->id,
            'produto_id'      => $produto->id,
            'quantidade'      => 5.0,
        ]);

        $service->cancelar($pedido);

        expect($produto->fresh()->estoque_reservado)->toBe(0.0)
            ->and($pedido->fresh()->status)->toBe('cancelado');
    });

    it('não cancela pedido já faturado', function () {
        $service = makeVendasService();
        $pedido = PedidoVenda::factory()->create(['status' => 'faturado']);

        expect(fn () => $service->cancelar($pedido))
            ->toThrow(\InvalidArgumentException::class, 'não pode ser cancelado');
    });

});

// ─── Margem ───────────────────────────────────────────────────────────────────

describe('PedidoVenda::margemTotal()', function () {

    it('calcula margem corretamente', function () {
        $service = makeVendasService();
        // Venda: 200 | Custo: 2 × 60 = 120 | Margem: (200-120)/200 = 40%
        $pedido = $service->criar(dadosPedido(), [
            itemPedido(['quantidade' => 2.0, 'preco_unitario' => 100.0])
        ]);

        expect($pedido->margemTotal())->toBe(40.0);
    });

});
