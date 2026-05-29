<?php

use App\Enums\NFeStatus;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\Pessoa;
use App\Services\Fiscal\NFeService;
use App\Services\Fiscal\NfeXmlBuilder;
use App\Services\Fiscal\SefazService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function criarEmpresaValida(): Empresa
{
    return Empresa::factory()->create([
        'cnpj' => '11.222.333/0001-81',
        'regime_tributario' => '1',
        'certificado_path' => 'certs/test.pfx',
        'certificado_senha' => encrypt('senha123'),
        'certificado_validade' => now()->addYear(),
        'rntrc' => '01234567',
        'ambiente_nfe' => 2,
        'serie_nfe' => 1,
        'numero_nfe' => 100,
    ]);
}

function criarNfeComItens(Empresa $empresa): Nfe
{
    $nfe = Nfe::factory()
        ->for($empresa)
        ->rascunho()
        ->create();

    \App\Models\NfeItem::factory()
        ->count(2)
        ->for($nfe)
        ->create();

    return $nfe;
}

function mockSefazAutorizacao(int $cStat = 100): array
{
    return [
        'cStat' => $cStat,
        'xMotivo' => $cStat === 100 ? 'Autorizado o uso da NF-e' : 'Rejeição: código inválido',
        'chave' => str_repeat('1', 44),
        'nProt' => '141240000000001',
        'xml' => '<nfeProc><NFe><infNFe/></NFe><protNFe><infProt><cStat>100</cStat></infProt></protNFe></nfeProc>',
    ];
}

// ─── Testes: emissão ─────────────────────────────────────────────────────────

describe('NFeService::emitir()', function () {

    it('autoriza uma NF-e válida na SEFAZ', function () {
        Storage::fake('s3');

        $empresa = criarEmpresaValida();
        $nfe = criarNfeComItens($empresa);

        $xmlBuilder = Mockery::mock(NfeXmlBuilder::class);
        $xmlBuilder->shouldReceive('build')->once()->andReturn('<xml>mock</xml>');

        $sefaz = Mockery::mock(SefazService::class);
        $sefaz->shouldReceive('autorizar')
              ->once()
              ->andReturn(mockSefazAutorizacao(100));

        $service = new NFeService($xmlBuilder, $sefaz);
        $result = $service->emitir($nfe);

        expect($result->status)->toBe(NFeStatus::AUTORIZADA)
            ->and($result->protocolo_autorizacao)->toBe('141240000000001')
            ->and($result->chave_acesso)->toHaveLength(44)
            ->and($result->data_autorizacao)->not->toBeNull();

        Storage::disk('s3')->assertExists($result->path_xml);
    });

    it('marca como rejeitada quando SEFAZ retorna código de rejeição', function () {
        $empresa = criarEmpresaValida();
        $nfe = criarNfeComItens($empresa);

        $xmlBuilder = Mockery::mock(NfeXmlBuilder::class);
        $xmlBuilder->shouldReceive('build')->once()->andReturn('<xml>mock</xml>');

        $sefaz = Mockery::mock(SefazService::class);
        $sefaz->shouldReceive('autorizar')
              ->once()
              ->andReturn([
                  'cStat' => 539,
                  'xMotivo' => 'Rejeição: CNPJ do emitente inválido',
                  'chave' => null,
                  'nProt' => null,
                  'xml' => null,
              ]);

        $service = new NFeService($xmlBuilder, $sefaz);
        $result = $service->emitir($nfe);

        expect($result->status)->toBe(NFeStatus::REJEITADA)
            ->and($result->motivo_rejeicao)->toContain('CNPJ do emitente inválido');
    });

    it('lança exceção se empresa não tem certificado', function () {
        $empresa = Empresa::factory()->create(['certificado_path' => null]);
        $nfe = criarNfeComItens($empresa);

        $service = new NFeService(
            Mockery::mock(NfeXmlBuilder::class),
            Mockery::mock(SefazService::class),
        );

        expect(fn () => $service->emitir($nfe))
            ->toThrow(\InvalidArgumentException::class, 'Certificado digital não configurado');
    });

    it('lança exceção se certificado está vencido', function () {
        $empresa = Empresa::factory()->create([
            'certificado_path' => 'certs/vencido.pfx',
            'certificado_validade' => now()->subDay(),
        ]);
        $nfe = criarNfeComItens($empresa);

        $service = new NFeService(
            Mockery::mock(NfeXmlBuilder::class),
            Mockery::mock(SefazService::class),
        );

        expect(fn () => $service->emitir($nfe))
            ->toThrow(\InvalidArgumentException::class, 'Certificado digital vencido');
    });

    it('lança exceção se NF-e não tem itens', function () {
        $empresa = criarEmpresaValida();
        $nfe = Nfe::factory()->for($empresa)->rascunho()->create();
        // sem itens

        $service = new NFeService(
            Mockery::mock(NfeXmlBuilder::class),
            Mockery::mock(SefazService::class),
        );

        expect(fn () => $service->emitir($nfe))
            ->toThrow(\InvalidArgumentException::class, 'não possui itens');
    });

    it('não permite emitir NF-e já autorizada', function () {
        $empresa = criarEmpresaValida();
        $nfe = Nfe::factory()->for($empresa)->autorizada()->create();

        $service = new NFeService(
            Mockery::mock(NfeXmlBuilder::class),
            Mockery::mock(SefazService::class),
        );

        expect(fn () => $service->emitir($nfe))
            ->toThrow(\InvalidArgumentException::class, 'não pode ser emitida');
    });

    it('incrementa tentativas_envio a cada tentativa', function () {
        $empresa = criarEmpresaValida();
        $nfe = criarNfeComItens($empresa);
        expect($nfe->tentativas_envio)->toBe(0);

        $xmlBuilder = Mockery::mock(NfeXmlBuilder::class);
        $xmlBuilder->shouldReceive('build')->andReturn('<xml>mock</xml>');

        $sefaz = Mockery::mock(SefazService::class);
        $sefaz->shouldReceive('autorizar')->andReturn(mockSefazAutorizacao(100));

        Storage::fake('s3');
        $service = new NFeService($xmlBuilder, $sefaz);
        $result = $service->emitir($nfe);

        expect($result->tentativas_envio)->toBe(1);
    });

});

// ─── Testes: cancelamento ────────────────────────────────────────────────────

describe('NFeService::cancelar()', function () {

    it('cancela uma NF-e autorizada com justificativa válida', function () {
        $empresa = criarEmpresaValida();
        $nfe = Nfe::factory()->for($empresa)->autorizada()->create([
            'data_autorizacao' => now()->subHours(2),
        ]);

        $sefaz = Mockery::mock(SefazService::class);
        $sefaz->shouldReceive('cancelar')->once()->andReturn([
            'protocolo' => '141240000000099',
            'xml' => '<cancNFe><infCanc/></cancNFe>',
        ]);

        $service = new NFeService(Mockery::mock(NfeXmlBuilder::class), $sefaz);
        $result = $service->cancelar($nfe, 'Cancelamento solicitado pelo cliente via pedido.');

        expect($result->status)->toBe(NFeStatus::CANCELADA)
            ->and($result->cancelada_em)->not->toBeNull()
            ->and($result->protocolo_cancelamento)->toBe('141240000000099');
    });

    it('rejeita cancelamento com justificativa curta', function () {
        $empresa = criarEmpresaValida();
        $nfe = Nfe::factory()->for($empresa)->autorizada()->create();

        $service = new NFeService(
            Mockery::mock(NfeXmlBuilder::class),
            Mockery::mock(SefazService::class),
        );

        expect(fn () => $service->cancelar($nfe, 'Curta'))
            ->toThrow(\InvalidArgumentException::class, 'mínimo 15 caracteres');
    });

    it('rejeita cancelamento de NF-e não autorizada', function () {
        $empresa = criarEmpresaValida();
        $nfe = Nfe::factory()->for($empresa)->rascunho()->create();

        $service = new NFeService(
            Mockery::mock(NfeXmlBuilder::class),
            Mockery::mock(SefazService::class),
        );

        expect(fn () => $service->cancelar($nfe, 'Justificativa longa o suficiente aqui'))
            ->toThrow(\InvalidArgumentException::class, 'autorizadas podem ser canceladas');
    });

    it('rejeita cancelamento fora do prazo', function () {
        $empresa = criarEmpresaValida();
        $nfe = Nfe::factory()->for($empresa)->autorizada()->create([
            'data_autorizacao' => now()->subHours(30),
        ]);

        $service = new NFeService(
            Mockery::mock(NfeXmlBuilder::class),
            Mockery::mock(SefazService::class),
        );

        expect(fn () => $service->cancelar($nfe, 'Justificativa longa o suficiente'))
            ->toThrow(\InvalidArgumentException::class, 'Prazo para cancelamento expirado');
    });

});

// ─── Testes: carta de correção ────────────────────────────────────────────────

describe('NFeService::cartaCorrecao()', function () {

    it('emite CC-e para NF-e autorizada', function () {
        $empresa = criarEmpresaValida();
        $nfe = Nfe::factory()->for($empresa)->autorizada()->create(['cce_sequencia' => 0]);

        $sefaz = Mockery::mock(SefazService::class);
        $sefaz->shouldReceive('cartaCorrecao')->once()->andReturn([
            'protocolo' => '141240000000077',
            'xml' => '<procCCe/>',
        ]);

        $service = new NFeService(Mockery::mock(NfeXmlBuilder::class), $sefaz);
        $result = $service->cartaCorrecao($nfe, 'Correção no endereço do destinatário, bairro incorreto.');

        expect($result->cce_sequencia)->toBe(1)
            ->and($result->protocolo_cce)->toBe('141240000000077');
    });

    it('rejeita CC-e com descrição curta', function () {
        $empresa = criarEmpresaValida();
        $nfe = Nfe::factory()->for($empresa)->autorizada()->create();

        $service = new NFeService(
            Mockery::mock(NfeXmlBuilder::class),
            Mockery::mock(SefazService::class),
        );

        expect(fn () => $service->cartaCorrecao($nfe, 'Corr.'))
            ->toThrow(\InvalidArgumentException::class, 'mínimo 15 caracteres');
    });

});
