<?php

use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\Pessoa;
use App\Models\Produto;
use App\Services\Estoque\EstoqueService;
use App\Services\Fiscal\NfseService;
use App\Services\Servicos\OrdemServicoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function osService(): OrdemServicoService
{
    return new OrdemServicoService(new EstoqueService(), new NfseService());
}

// ─── Criação ────────────────────────────────────────────────────────────────

describe('OrdemServicoService::criar()', function () {

    it('cria OS como orçamento com número gerado', function () {
        $cliente = Pessoa::factory()->cliente()->create();
        $os = osService()->criar([
            'empresa_id' => 1,
            'cliente_id' => $cliente->id,
            'descricao_problema' => 'Equipamento não liga',
        ]);

        expect($os->situacao)->toBe('orcamento')
            ->and($os->numero)->toStartWith('OS-')
            ->and($os->cliente_id)->toBe($cliente->id);
    });

    it('cria OS com itens e calcula total', function () {
        $cliente = Pessoa::factory()->cliente()->create();
        $os = osService()->criar(
            ['empresa_id' => 1, 'cliente_id' => $cliente->id],
            [
                ['tipo' => 'servico', 'descricao' => 'Mão de obra', 'quantidade' => 1, 'valor_unitario' => 150],
                ['tipo' => 'peca', 'descricao' => 'Fonte', 'quantidade' => 1, 'valor_unitario' => 200],
            ]
        );

        expect($os->total_geral)->toBe(350.0)
            ->and($os->total_servicos)->toBe(150.0)
            ->and($os->total_pecas)->toBe(200.0)
            ->and($os->itens)->toHaveCount(2);
    });

    it('registra histórico ao criar', function () {
        $cliente = Pessoa::factory()->cliente()->create();
        $os = osService()->criar(['empresa_id' => 1, 'cliente_id' => $cliente->id]);

        expect($os->historico()->count())->toBeGreaterThan(0);
    });

});

// ─── Aprovação ────────────────────────────────────────────────────────────────

describe('OrdemServicoService::aprovar()', function () {

    it('aprova orçamento', function () {
        $os = OrdemServico::factory()->create(['situacao' => 'orcamento']);
        $resultado = osService()->aprovar($os);

        expect($resultado->situacao)->toBe('aprovada')
            ->and($resultado->data_aprovacao_cliente)->not->toBeNull();
    });

    it('não aprova OS que não é orçamento', function () {
        $os = OrdemServico::factory()->create(['situacao' => 'concluida']);
        expect(fn () => osService()->aprovar($os))
            ->toThrow(\InvalidArgumentException::class, 'Apenas orçamentos');
    });

});

// ─── Finalização e integrações ────────────────────────────────────────────────

describe('OrdemServicoService::finalizar()', function () {

    it('baixa estoque das peças ao finalizar', function () {
        $cliente = Pessoa::factory()->cliente()->create();
        $produto = Produto::factory()->create(['estoque_atual' => 10, 'controla_estoque' => true]);
        $os = OrdemServico::factory()->aprovada()->create(['cliente_id' => $cliente->id]);

        OrdemServicoItem::factory()->peca()->create([
            'ordem_servico_id' => $os->id,
            'produto_id'       => $produto->id,
            'quantidade'       => 3,
            'aprovado'         => true,
        ]);

        osService()->finalizar($os, ['gerar_nfse' => false, 'gerar_financeiro' => false]);

        expect($produto->fresh()->estoque_atual)->toBe(7.0)
            ->and($os->fresh()->estoque_baixado)->toBeTrue();
    });

    it('gera lançamento financeiro ao finalizar', function () {
        $cliente = Pessoa::factory()->cliente()->create();
        $os = OrdemServico::factory()->aprovada()->create([
            'cliente_id'  => $cliente->id,
            'total_geral' => 500,
        ]);
        OrdemServicoItem::factory()->servico()->create([
            'ordem_servico_id' => $os->id, 'total' => 500, 'aprovado' => true,
        ]);

        $resultado = osService()->finalizar($os, ['gerar_nfse' => false, 'gerar_financeiro' => true]);

        expect($resultado->lancamento_id)->not->toBeNull();
        $lancamento = \App\Models\Lancamento::find($resultado->lancamento_id);
        expect($lancamento->tipo)->toBe('receber')
            ->and((float) $lancamento->valor_original)->toBe(500.0);
    });

    it('gera NFS-e dos serviços ao finalizar', function () {
        $cliente = Pessoa::factory()->cliente()->create();
        $os = OrdemServico::factory()->aprovada()->create(['cliente_id' => $cliente->id]);
        OrdemServicoItem::factory()->servico()->create([
            'ordem_servico_id' => $os->id, 'total' => 300, 'aprovado' => true,
        ]);
        $os->recalcularTotais();

        $resultado = osService()->finalizar($os, ['gerar_nfse' => true, 'gerar_financeiro' => false]);

        expect($resultado->nfse_id)->not->toBeNull();
    });

    it('define garantia ao finalizar', function () {
        $cliente = Pessoa::factory()->cliente()->create();
        $os = OrdemServico::factory()->aprovada()->create([
            'cliente_id' => $cliente->id, 'garantia_dias' => 90,
        ]);

        $resultado = osService()->finalizar($os, ['gerar_nfse' => false, 'gerar_financeiro' => false]);

        expect($resultado->garantia_ate)->not->toBeNull()
            ->and($resultado->situacao)->toBe('concluida');
    });

    it('não finaliza OS em orçamento', function () {
        $os = OrdemServico::factory()->create(['situacao' => 'orcamento']);
        expect(fn () => osService()->finalizar($os))
            ->toThrow(\InvalidArgumentException::class, 'não pode ser finalizada');
    });

});

// ─── Cancelamento ─────────────────────────────────────────────────────────────

describe('OrdemServicoService::cancelar()', function () {

    it('cancela OS em orçamento', function () {
        $os = OrdemServico::factory()->create(['situacao' => 'orcamento']);
        $resultado = osService()->cancelar($os, 'Cliente desistiu');

        expect($resultado->situacao)->toBe('cancelada');
    });

    it('não cancela OS concluída', function () {
        $os = OrdemServico::factory()->create(['situacao' => 'concluida']);
        expect(fn () => osService()->cancelar($os))
            ->toThrow(\InvalidArgumentException::class, 'não pode ser cancelada');
    });

});
