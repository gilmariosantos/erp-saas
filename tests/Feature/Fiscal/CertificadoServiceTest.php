<?php

use App\Models\Empresa;
use App\Services\Fiscal\Certificado\CertificadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * NOTA: testes que exigem um .pfx real são marcados como integração e
 * rodam apenas no servidor do cliente com certificado válido.
 * Aqui testamos a lógica de validação, segurança e fluxo sem certificado real.
 */

describe('CertificadoService::validar()', function () {

    it('retorna inválido para conteúdo corrompido', function () {
        $service = new CertificadoService();
        $resultado = $service->validar('conteudo-que-nao-eh-pfx', 'senha123');

        expect($resultado['valido'])->toBeFalse()
            ->and($resultado['erro'])->not->toBeNull();
    });

    it('não vaza a senha na resposta de erro', function () {
        $service = new CertificadoService();
        $resultado = $service->validar('invalido', 'minhaSenhaSecreta123');

        $json = json_encode($resultado);
        expect($json)->not->toContain('minhaSenhaSecreta123');
    });

    it('mensagem de erro é genérica (não expõe detalhes internos)', function () {
        $service = new CertificadoService();
        $resultado = $service->validar('xyz', 'senha');

        // Deve ser uma das mensagens amigáveis, não um stack trace
        expect($resultado['erro'])->toBeIn([
            'Senha do certificado incorreta.',
            'Arquivo de certificado inválido ou corrompido.',
        ]);
    });

});

describe('CertificadoService::certificadosVencendo()', function () {

    it('lista certificados que vencem dentro do prazo', function () {
        Empresa::factory()->create([
            'razao_social'         => 'Empresa Vence Logo',
            'certificado_validade' => now()->addDays(15),
            'is_active'            => true,
        ]);
        Empresa::factory()->create([
            'razao_social'         => 'Empresa OK',
            'certificado_validade' => now()->addDays(200),
            'is_active'            => true,
        ]);

        $service = new CertificadoService();
        $vencendo = $service->certificadosVencendo(30);

        expect($vencendo)->toHaveCount(1)
            ->and($vencendo[0]['razao_social'])->toBe('Empresa Vence Logo');
    });

    it('inclui certificados já vencidos', function () {
        Empresa::factory()->create([
            'certificado_validade' => now()->subDays(5),
            'is_active'            => true,
        ]);

        $service = new CertificadoService();
        $vencendo = $service->certificadosVencendo(30);

        expect($vencendo)->toHaveCount(1)
            ->and($vencendo[0]['vencido'])->toBeTrue();
    });

    it('ignora empresas sem certificado', function () {
        Empresa::factory()->create(['certificado_validade' => null, 'is_active' => true]);

        $service = new CertificadoService();
        expect($service->certificadosVencendo(30))->toHaveCount(0);
    });

});

describe('CertificadoService::remover()', function () {

    it('limpa os dados do certificado da empresa', function () {
        Storage::fake('s3');
        Storage::disk('s3')->put('certs/teste.pfx.enc', 'conteudo');

        $empresa = Empresa::factory()->create([
            'certificado_path'     => 'certs/teste.pfx.enc',
            'certificado_validade' => now()->addYear(),
        ]);

        $service = new CertificadoService();
        $service->remover($empresa);

        expect($empresa->fresh()->certificado_path)->toBeNull()
            ->and($empresa->fresh()->certificado_validade)->toBeNull();
        Storage::disk('s3')->assertMissing('certs/teste.pfx.enc');
    });

});

describe('Endpoint de upload de certificado', function () {

    it('exige arquivo e senha', function () {
        $user = \App\Models\User::factory()->create();
        $response = $this->actingAs($user)->postJson('/api/v1/fiscal/certificado/validar', []);
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['certificado', 'senha']);
    });

});
