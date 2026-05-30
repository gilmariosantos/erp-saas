<?php

use App\Models\Empresa;
use App\Services\Fiscal\NfseService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('NfseService::calcularImpostos()', function () {

    it('calcula ISS retido corretamente', function () {
        $empresa = Empresa::factory()->create(['aliquota_iss' => 5.0]);
        $service = new NfseService();

        $resultado = $service->calcularImpostos(1000.0, $empresa, 5.0, issRetido: true);

        expect($resultado['iss'])->toBe(50.0)
            ->and($resultado['valorLiquido'])->toBe(950.0);
    });

    it('calcula múltiplas retenções simultâneas', function () {
        $empresa = Empresa::factory()->create(['aliquota_iss' => 3.0]);
        $service = new NfseService();

        $resultado = $service->calcularImpostos(
            1000.0, $empresa, 3.0,
            issRetido: true,
            pisRetido: true,
            cofinsRetido: true,
        );

        // ISS 3% = 30, PIS 0.65% = 6.50, COFINS 3% = 30
        expect($resultado['iss'])->toBe(30.0)
            ->and($resultado['pis'])->toBe(6.5)
            ->and($resultado['cofins'])->toBe(30.0)
            ->and($resultado['valorLiquido'])->toBe(933.5);
    });

    it('retorna valor cheio quando não há retenções', function () {
        $empresa = Empresa::factory()->create(['aliquota_iss' => 5.0]);
        $service = new NfseService();

        $resultado = $service->calcularImpostos(2000.0, $empresa);

        expect($resultado['iss'])->toBe(0.0)
            ->and($resultado['valorLiquido'])->toBe(2000.0)
            ->and($resultado['totalRetencoes'])->toBe(0.0);
    });

});

describe('NfseService::detectarPadrao()', function () {

    it('detecta padrão paulistana para São Paulo', function () {
        $service = new NfseService();
        expect($service->detectarPadrao('3550308'))->toBe('paulistana');
    });

    it('detecta padrão GINFES para Londrina', function () {
        $service = new NfseService();
        expect($service->detectarPadrao('4113700'))->toBe('ginfes');
    });

    it('retorna ABRASF como padrão para municípios desconhecidos', function () {
        $service = new NfseService();
        expect($service->detectarPadrao('9999999'))->toBe('abrasf');
    });

    it('detecta padrão Betha para Blumenau', function () {
        $service = new NfseService();
        expect($service->detectarPadrao('4202404'))->toBe('betha');
    });

});

describe('NfseService::emitir() — validações', function () {

    it('lança exceção se valor do serviço é zero', function () {
        $empresa = Empresa::factory()->create(['cnpj' => '11.222.333/0001-81']);
        $nfse = \App\Models\Nfse::factory()->create([
            'empresa_id'    => $empresa->id,
            'valor_servico' => 0,
            'codigo_servico'=> '1.01',
        ]);

        $service = new NfseService();

        expect(fn () => $service->emitir($nfse))
            ->toThrow(\InvalidArgumentException::class, 'maior que zero');
    });

});
