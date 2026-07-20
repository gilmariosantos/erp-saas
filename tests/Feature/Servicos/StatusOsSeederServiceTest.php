<?php

use App\Models\StatusOs;
use App\Models\TipoOs;
use App\Services\Servicos\StatusOsSeederService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Valida o seeder que roda no provisionamento de cada empresa,
 * criando o fluxo de status e o tipo padrão da Ordem de Serviço.
 */

describe('StatusOsSeederService', function () {

    it('cria os 6 status padrão da OS', function () {
        (new StatusOsSeederService())->criarPadroes(empresaId: 1);

        expect(StatusOs::where('empresa_id', 1)->count())->toBe(6);
    });

    it('define exatamente um status inicial', function () {
        (new StatusOsSeederService())->criarPadroes(1);

        expect(StatusOs::where('empresa_id', 1)->where('is_inicial', true)->count())->toBe(1);
    });

    it('marca o status inicial como "Aberta"', function () {
        (new StatusOsSeederService())->criarPadroes(1);

        $inicial = StatusOs::where('empresa_id', 1)->where('is_inicial', true)->first();
        expect($inicial->nome)->toBe('Aberta');
    });

    it('define um status final e um de cancelamento', function () {
        (new StatusOsSeederService())->criarPadroes(1);

        expect(StatusOs::where('empresa_id', 1)->where('is_final', true)->count())->toBe(1)
            ->and(StatusOs::where('empresa_id', 1)->where('is_cancelado', true)->count())->toBe(1);
    });

    it('cria um tipo de OS padrão', function () {
        (new StatusOsSeederService())->criarPadroes(1);

        expect(TipoOs::where('empresa_id', 1)->count())->toBe(1);
    });

    it('é idempotente — não duplica ao rodar duas vezes', function () {
        $service = new StatusOsSeederService();
        $service->criarPadroes(1);
        $service->criarPadroes(1);

        // firstOrCreate garante que não duplica
        expect(StatusOs::where('empresa_id', 1)->count())->toBe(6)
            ->and(TipoOs::where('empresa_id', 1)->count())->toBe(1);
    });

    it('isola status por empresa', function () {
        $service = new StatusOsSeederService();
        $service->criarPadroes(1);
        $service->criarPadroes(2);

        expect(StatusOs::where('empresa_id', 1)->count())->toBe(6)
            ->and(StatusOs::where('empresa_id', 2)->count())->toBe(6);
    });

});
