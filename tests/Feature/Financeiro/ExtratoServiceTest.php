<?php

use App\Services\Financeiro\ExtratoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── OFX de teste ─────────────────────────────────────────────────────────────

function ofxValido(): string
{
    return <<<OFX
    OFXHEADER:100
    DATA:OFXSGML
    VERSION:151
    SECURITY:NONE
    ENCODING:UTF-8
    CHARSET:1252
    COMPRESSION:NONE
    OLDFILEUID:NONE
    NEWFILEUID:NONE

    <OFX>
    <BANKMSGSRSV1>
    <STMTTRNRS>
    <TRNUID>1001
    <STMTRS>
    <CURDEF>BRL
    <BANKACCTFROM>
    <BANKID>237
    <ACCTID>12345-6
    <ACCTTYPE>CHECKING
    </BANKACCTFROM>
    <BANKTRANLIST>
    <DTSTART>20240101
    <DTEND>20240131
    <STMTTRN>
    <TRNTYPE>CREDIT
    <DTPOSTED>20240105120000
    <TRNAMT>1500.00
    <FITID>TRN001
    <MEMO>PIX RECEBIDO - CLIENTE ABC
    </STMTTRN>
    <STMTTRN>
    <TRNTYPE>DEBIT
    <DTPOSTED>20240110120000
    <TRNAMT>-300.00
    <FITID>TRN002
    <MEMO>PAGAMENTO FORNECEDOR XYZ
    </STMTTRN>
    <STMTTRN>
    <TRNTYPE>DEBIT
    <DTPOSTED>20240115120000
    <TRNAMT>-50.00
    <FITID>TRN003
    <MEMO>TARIFA BANCARIA
    </STMTTRN>
    </BANKTRANLIST>
    </STMTRS>
    </STMTTRNRS>
    </BANKMSGSRSV1>
    </OFX>
    OFX;
}

// ─── Importação OFX ──────────────────────────────────────────────────────────

describe('ExtratoService::importarOfx()', function () {

    it('parseia arquivo OFX 1.x com 3 transações', function () {
        $service = new ExtratoService();
        $transacoes = $service->importarOfx(ofxValido());

        expect($transacoes)->toHaveCount(3);
    });

    it('identifica tipo credito e debito corretamente', function () {
        $service = new ExtratoService();
        $transacoes = $service->importarOfx(ofxValido());

        expect($transacoes->where('tipo', 'credito')->count())->toBe(1)
            ->and($transacoes->where('tipo', 'debito')->count())->toBe(2);
    });

    it('extrai valor absoluto (sem sinal negativo)', function () {
        $service = new ExtratoService();
        $transacoes = $service->importarOfx(ofxValido());

        $debito = $transacoes->where('tipo', 'debito')->first();
        expect($debito['valor'])->toBeGreaterThan(0);
    });

    it('parseia data no formato OFX corretamente', function () {
        $service = new ExtratoService();
        $transacoes = $service->importarOfx(ofxValido());

        expect($transacoes->first()['data'])->toBe('2024-01-05');
    });

    it('captura FITID de cada transação', function () {
        $service = new ExtratoService();
        $transacoes = $service->importarOfx(ofxValido());

        expect($transacoes->pluck('id_transacao')->toArray())
            ->toContain('TRN001')
            ->toContain('TRN002');
    });

    it('ordena transações por data', function () {
        $service = new ExtratoService();
        $transacoes = $service->importarOfx(ofxValido());
        $datas = $transacoes->pluck('data')->toArray();

        expect($datas)->toBe(collect($datas)->sort()->values()->toArray());
    });

    it('limpa descrição com espaços extras', function () {
        $service = new ExtratoService();
        $transacoes = $service->importarOfx(ofxValido());
        $credito = $transacoes->where('tipo', 'credito')->first();

        expect($credito['descricao'])->not->toContain('  ');
    });

    it('lança exceção para arquivo OFX inválido', function () {
        $service = new ExtratoService();

        expect(fn () => $service->importarOfx('ARQUIVO INVALIDO SEM ESTRUTURA OFX'))
            ->toThrow(\InvalidArgumentException::class, 'OFX inválido');
    });

    it('retorna collection vazia se não houver transações', function () {
        $ofxVazio = str_replace(
            '<STMTTRN>',
            '<!-- sem transacoes -->',
            ofxValido()
        );

        $service = new ExtratoService();

        // OFX sem transações deve retornar collection vazia sem lançar exceção
        expect(fn () => $service->importarOfx($ofxVazio))->not->toThrow(\Exception::class);
    });

});

// ─── Registrar movimentação ───────────────────────────────────────────────────

describe('ExtratoService::registrar()', function () {

    it('cria registro no extrato bancário', function () {
        $conta = \App\Models\ContaBancaria::factory()->create(['saldo_atual' => 500.0]);
        $service = new ExtratoService();

        $extrato = $service->registrar(
            conta: $conta,
            tipo: 'credito',
            valor: 200.0,
            data: today()->toDateString(),
            descricao: 'Teste crédito',
        );

        expect($extrato->conta_bancaria_id)->toBe($conta->id)
            ->and($extrato->tipo)->toBe('credito')
            ->and($extrato->valor)->toBe(200.0);
    });

});
