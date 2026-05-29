<?php

use App\Enums\NFeStatus;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeItem;
use App\Models\User;
use App\Services\Fiscal\NFeService;
use App\Services\Fiscal\NfeXmlBuilder;
use App\Services\Fiscal\SefazService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->user);
    Storage::fake('s3');
    Queue::fake();
});

// ─── GET /api/v1/fiscal/nfes ─────────────────────────────────────────────────

describe('GET /api/v1/fiscal/nfes', function () {

    it('lista NF-es com paginação', function () {
        Nfe::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/fiscal/nfes');

        $response->assertOk()
                 ->assertJsonStructure(['data', 'links', 'meta']);
    });

    it('filtra por status', function () {
        Nfe::factory()->autorizada()->create();
        Nfe::factory()->rascunho()->create();

        $response = $this->getJson('/api/v1/fiscal/nfes?status=autorizada');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    });

});

// ─── POST /api/v1/fiscal/nfes ────────────────────────────────────────────────

describe('POST /api/v1/fiscal/nfes', function () {

    it('cria NF-e em rascunho', function () {
        $empresa = Empresa::factory()->create();

        $response = $this->postJson('/api/v1/fiscal/nfes', [
            'empresa_id'               => $empresa->id,
            'natureza_operacao'        => 'VENDA DE MERCADORIA',
            'data_emissao'             => now()->toDateTimeString(),
            'destinatario_cnpj_cpf'    => '11.222.333/0001-81',
            'destinatario_nome'        => 'Cliente Teste Ltda',
            'destinatario_uf'          => 'SP',
            'destinatario_indicador_ie'=> 9,
            'modalidade_frete'         => '9',
        ]);

        $response->assertCreated()
                 ->assertJsonPath('data.status', 'rascunho');
    });

    it('incrementa número_nfe da empresa ao criar', function () {
        $empresa = Empresa::factory()->create(['numero_nfe' => 5]);

        $this->postJson('/api/v1/fiscal/nfes', [
            'empresa_id'               => $empresa->id,
            'natureza_operacao'        => 'VENDA',
            'data_emissao'             => now()->toDateTimeString(),
            'destinatario_cnpj_cpf'    => '12345678000195',
            'destinatario_nome'        => 'Teste',
            'destinatario_uf'          => 'RJ',
            'destinatario_indicador_ie'=> 9,
            'modalidade_frete'         => '9',
        ]);

        expect($empresa->fresh()->numero_nfe)->toBe(6);
    });

});

// ─── POST /api/v1/fiscal/nfes/{nfe}/emitir ───────────────────────────────────

describe('POST /api/v1/fiscal/nfes/{nfe}/emitir', function () {

    it('despacha job EmitirNfe e retorna 200', function () {
        $nfe = Nfe::factory()->rascunho()->create();
        NfeItem::factory()->for($nfe)->create();

        $response = $this->postJson("/api/v1/fiscal/nfes/{$nfe->id}/emitir");

        $response->assertOk()
                 ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'processamento'));

        Queue::assertPushed(\App\Jobs\EmitirNfe::class);
    });

    it('não emite NF-e já autorizada', function () {
        $nfe = Nfe::factory()->autorizada()->create();

        $response = $this->postJson("/api/v1/fiscal/nfes/{$nfe->id}/emitir");

        $response->assertStatus(422);
        Queue::assertNothingPushed();
    });

});

// ─── POST /api/v1/fiscal/nfes/{nfe}/cancelar ─────────────────────────────────

describe('POST /api/v1/fiscal/nfes/{nfe}/cancelar', function () {

    it('cancela NF-e autorizada via service', function () {
        $nfe = Nfe::factory()->autorizada()->create([
            'data_autorizacao' => now()->subHour(),
        ]);

        $sefaz = Mockery::mock(SefazService::class);
        $sefaz->shouldReceive('cancelar')->once()->andReturn([
            'protocolo' => '999',
            'xml'       => '<cancNFe/>',
        ]);
        app()->instance(SefazService::class, $sefaz);
        app()->bind(NFeService::class, fn ($app) => new NFeService(
            new NfeXmlBuilder(), $app->make(SefazService::class)
        ));

        $response = $this->postJson("/api/v1/fiscal/nfes/{$nfe->id}/cancelar", [
            'justificativa' => 'Cancelamento solicitado pelo cliente via sistema.',
        ]);

        $response->assertOk()
                 ->assertJsonPath('data.status', 'cancelada');
    });

    it('rejeita justificativa com menos de 15 caracteres', function () {
        $nfe = Nfe::factory()->autorizada()->create();

        $response = $this->postJson("/api/v1/fiscal/nfes/{$nfe->id}/cancelar", [
            'justificativa' => 'Curta',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors('justificativa');
    });

});

// ─── GET /api/v1/fiscal/nfes/{nfe}/xml ───────────────────────────────────────

describe('GET /api/v1/fiscal/nfes/{nfe}/xml', function () {

    it('retorna URL assinada do XML', function () {
        $nfe = Nfe::factory()->autorizada()->create(['path_xml' => 'xmls/test.xml']);
        Storage::disk('s3')->put('xmls/test.xml', '<nfeProc/>');

        $response = $this->getJson("/api/v1/fiscal/nfes/{$nfe->id}/xml");

        $response->assertOk()->assertJsonStructure(['url', 'expires_in']);
    });

    it('retorna 404 quando XML não existe', function () {
        $nfe = Nfe::factory()->autorizada()->create(['path_xml' => null]);

        $response = $this->getJson("/api/v1/fiscal/nfes/{$nfe->id}/xml");

        $response->assertNotFound();
    });

});
