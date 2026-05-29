<?php

use App\Enums\CTeStatus;
use App\Models\Cte;
use App\Models\Empresa;
use App\Services\Fiscal\CTeService;
use App\Services\Fiscal\CTeXmlBuilder;
use App\Services\Fiscal\SefazService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function criarEmpresaTransportadora(): Empresa
{
    return Empresa::factory()->create([
        'cnpj' => '22.333.444/0001-55',
        'rntrc' => '01234567',
        'certificado_path' => 'certs/test.pfx',
        'certificado_senha' => encrypt('senha123'),
        'certificado_validade' => now()->addYear(),
        'ambiente_cte' => 2,
    ]);
}

function respostaCiotSoap(string $ciot = '123456789012', string $status = '0'): string
{
    return <<<XML
    <?xml version="1.0" encoding="utf-8"?>
    <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
      <soap:Body>
        <GerarCIOTResponse xmlns="http://tempuri.org/">
          <GerarCIOTResult>
            <CIOT>{$ciot}</CIOT>
            <Protocolo>PROT-2024-001</Protocolo>
            <Status>{$status}</Status>
            <Descricao>CIOT gerado com sucesso</Descricao>
          </GerarCIOTResult>
        </GerarCIOTResponse>
      </soap:Body>
    </soap:Envelope>
    XML;
}

// ─── Testes: emissão CT-e ─────────────────────────────────────────────────────

describe('CTeService::emitir()', function () {

    it('autoriza CT-e válido na SEFAZ', function () {
        Storage::fake('s3');

        $empresa = criarEmpresaTransportadora();
        $cte = Cte::factory()->for($empresa)->rascunho()->create([
            'remetente_cnpj_cpf' => '11.222.333/0001-81',
            'destinatario_cnpj_cpf' => '44.555.666/0001-77',
            'valor_total_servico' => 500.00,
        ]);

        $xmlBuilder = Mockery::mock(CTeXmlBuilder::class);
        $xmlBuilder->shouldReceive('build')->once()->andReturn('<cteProc/>');

        $sefaz = Mockery::mock(SefazService::class);
        $sefaz->shouldReceive('autorizarCte')->once()->andReturn([
            'cStat' => 100,
            'xMotivo' => 'Autorizado o uso do CT-e',
            'chave' => str_repeat('9', 44),
            'nProt' => '341240000000001',
            'xml' => '<cteProc/>',
        ]);

        $service = new CTeService($xmlBuilder, $sefaz);
        $result = $service->emitir($cte);

        expect($result->status)->toBe(CTeStatus::AUTORIZADA)
            ->and($result->protocolo_autorizacao)->toBe('341240000000001');
    });

    it('lança exceção se empresa não tem RNTRC', function () {
        $empresa = Empresa::factory()->create([
            'rntrc' => null,
            'certificado_path' => 'certs/test.pfx',
            'certificado_validade' => now()->addYear(),
        ]);
        $cte = Cte::factory()->for($empresa)->rascunho()->create([
            'remetente_cnpj_cpf' => '11.222.333/0001-81',
            'destinatario_cnpj_cpf' => '44.555.666/0001-77',
            'valor_total_servico' => 100.00,
        ]);

        $service = new CTeService(
            Mockery::mock(CTeXmlBuilder::class),
            Mockery::mock(SefazService::class),
        );

        expect(fn () => $service->emitir($cte))
            ->toThrow(\InvalidArgumentException::class, 'RNTRC');
    });

    it('lança exceção se valor do serviço é zero', function () {
        $empresa = criarEmpresaTransportadora();
        $cte = Cte::factory()->for($empresa)->rascunho()->create([
            'remetente_cnpj_cpf' => '11.222.333/0001-81',
            'destinatario_cnpj_cpf' => '44.555.666/0001-77',
            'valor_total_servico' => 0,
        ]);

        $service = new CTeService(
            Mockery::mock(CTeXmlBuilder::class),
            Mockery::mock(SefazService::class),
        );

        expect(fn () => $service->emitir($cte))
            ->toThrow(\InvalidArgumentException::class, 'Valor do serviço deve ser maior que zero');
    });

});

// ─── Testes: CIOT ─────────────────────────────────────────────────────────────

describe('CTeService::gerarCiot()', function () {

    it('gera CIOT com sucesso via ANTT', function () {
        $empresa = criarEmpresaTransportadora();

        Http::fake([
            '*antt.gov.br*' => Http::response(respostaCiotSoap('123456789012'), 200),
        ]);

        $service = new CTeService(
            Mockery::mock(CTeXmlBuilder::class),
            Mockery::mock(SefazService::class),
        );

        $result = $service->gerarCiot(
            cpfCnpjContratado: '123.456.789-00',
            cpfCnpjContratante: '11.222.333/0001-81',
            valorFrete: 1500.00,
            valorPedagio: 80.00,
            placaVeiculo: 'ABC1D23',
            ufOrigem: 'SP',
            ufDestino: 'RJ',
            empresa: $empresa,
        );

        expect($result['ciot'])->toBe('123456789012')
            ->and($result['protocolo'])->toBe('PROT-2024-001')
            ->and($result['status'])->toBe('0');
    });

    it('lança exceção quando ANTT retorna HTTP 500', function () {
        $empresa = criarEmpresaTransportadora();

        Http::fake([
            '*antt.gov.br*' => Http::response('Internal Server Error', 500),
        ]);

        $service = new CTeService(
            Mockery::mock(CTeXmlBuilder::class),
            Mockery::mock(SefazService::class),
        );

        expect(fn () => $service->gerarCiot(
            cpfCnpjContratado: '123.456.789-00',
            cpfCnpjContratante: '11.222.333/0001-81',
            valorFrete: 1500.00,
            valorPedagio: 0,
            placaVeiculo: 'ABC1D23',
            ufOrigem: 'SP',
            ufDestino: 'MG',
            empresa: $empresa,
        ))->toThrow(\RuntimeException::class, 'ANTT retornou erro HTTP');
    });

    it('normaliza CPF/CNPJ removendo formatação antes de enviar', function () {
        $empresa = criarEmpresaTransportadora();
        $cpfEnviado = null;

        Http::fake(function ($request) use (&$cpfEnviado) {
            $body = $request->body();
            preg_match('/<tem:CPFCNPJContratado>(.*?)<\/tem:CPFCNPJContratado>/', $body, $matches);
            $cpfEnviado = $matches[1] ?? null;
            return Http::response(respostaCiotSoap(), 200);
        });

        $service = new CTeService(
            Mockery::mock(CTeXmlBuilder::class),
            Mockery::mock(SefazService::class),
        );

        $service->gerarCiot(
            cpfCnpjContratado: '123.456.789-00',  // com formatação
            cpfCnpjContratante: '11.222.333/0001-81',
            valorFrete: 500.00,
            valorPedagio: 0,
            placaVeiculo: 'XYZ9A87',
            ufOrigem: 'GO',
            ufDestino: 'DF',
            empresa: $empresa,
        );

        expect($cpfEnviado)->toBe('12345678900'); // sem formatação
    });

});

// ─── Testes: cancelamento CT-e ────────────────────────────────────────────────

describe('CTeService::cancelar()', function () {

    it('cancela CT-e autorizado com justificativa válida', function () {
        $empresa = criarEmpresaTransportadora();
        $cte = Cte::factory()->for($empresa)->autorizada()->create();

        $sefaz = Mockery::mock(SefazService::class);
        $sefaz->shouldReceive('cancelarCte')->once()->andReturn([
            'xml' => '<cancCTe/>',
        ]);

        $service = new CTeService(Mockery::mock(CTeXmlBuilder::class), $sefaz);
        $result = $service->cancelar($cte, 'Cancelamento a pedido do remetente da carga.');

        expect($result->status)->toBe(CTeStatus::CANCELADA)
            ->and($result->cancelada_em)->not->toBeNull();
    });

    it('rejeita cancelamento com justificativa insuficiente', function () {
        $empresa = criarEmpresaTransportadora();
        $cte = Cte::factory()->for($empresa)->autorizada()->create();

        $service = new CTeService(
            Mockery::mock(CTeXmlBuilder::class),
            Mockery::mock(SefazService::class),
        );

        expect(fn () => $service->cancelar($cte, 'Erro'))
            ->toThrow(\InvalidArgumentException::class, 'mínimo 15 caracteres');
    });

});
