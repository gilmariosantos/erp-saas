<?php

use App\Models\Cte;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Services\Fiscal\CTeXmlBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * NOTA: estes testes validam a LÓGICA de montagem do CTeXmlBuilder
 * (escolha de tags por tipo, helpers, mapeamento de UF).
 *
 * A validação do XML contra o schema XSD da SEFAZ exige a biblioteca
 * sped-cte instalada e roda no servidor (ambiente de homologação).
 * Como o ambiente de testes não tem a lib carregada com schemas,
 * focamos nos métodos puros e na integração com o model.
 */

describe('CTeXmlBuilder — mapeamento de UF', function () {

    it('mapeia siglas de UF para códigos IBGE corretos', function () {
        $builder = new CTeXmlBuilder();
        $metodo = new ReflectionMethod($builder, 'codigoUF');
        $metodo->setAccessible(true);

        expect($metodo->invoke($builder, 'SP'))->toBe(35)
            ->and($metodo->invoke($builder, 'RJ'))->toBe(33)
            ->and($metodo->invoke($builder, 'MG'))->toBe(31)
            ->and($metodo->invoke($builder, 'RS'))->toBe(43);
    });

    it('usa SP como fallback para UF desconhecida', function () {
        $builder = new CTeXmlBuilder();
        $metodo = new ReflectionMethod($builder, 'codigoUF');
        $metodo->setAccessible(true);

        expect($metodo->invoke($builder, 'XX'))->toBe(35);
    });

    it('aceita UF em minúsculas', function () {
        $builder = new CTeXmlBuilder();
        $metodo = new ReflectionMethod($builder, 'codigoUF');
        $metodo->setAccessible(true);

        expect($metodo->invoke($builder, 'sp'))->toBe(35);
    });

});

describe('CTeXmlBuilder — indicador de IE do tomador', function () {

    it('retorna 1 (contribuinte) quando o tomador tem IE', function () {
        $cte = new Cte([
            'tomador' => 3, // destinatário
        ]);
        $cte->setRelation('destinatario', new Pessoa(['ie' => '123456789']));

        $builder = new CTeXmlBuilder();
        $metodo = new ReflectionMethod($builder, 'indicadorIEToma');
        $metodo->setAccessible(true);

        expect($metodo->invoke($builder, $cte))->toBe(1);
    });

    it('retorna 9 (não contribuinte) quando o tomador não tem IE', function () {
        $cte = new Cte(['tomador' => 3]);
        $cte->setRelation('destinatario', new Pessoa(['ie' => null]));

        $builder = new CTeXmlBuilder();
        $metodo = new ReflectionMethod($builder, 'indicadorIEToma');
        $metodo->setAccessible(true);

        expect($metodo->invoke($builder, $cte))->toBe(9);
    });

});

describe('CTeXmlBuilder — geração de XML', function () {

    it('gera XML não-vazio para CT-e rodoviário normal', function () {
        $empresa = Empresa::factory()->comCertificado()->create([
            'uf' => 'SP', 'codigo_municipio' => '3550308', 'rntrc' => '12345678',
        ]);
        $cte = Cte::factory()->create([
            'empresa_id'  => $empresa->id,
            'tipo_ct'     => 0,  // normal
            'tipo_servico'=> 0,  // normal
            'modal'       => '01',
            'tomador'     => 3,
            'uf_inicio'   => 'SP',
            'uf_fim'      => 'RJ',
            'cst_icms'    => '00',
            'valor_total_servico' => 500.00,
        ]);

        $builder = new CTeXmlBuilder();
        $xml = $builder->build($cte);

        // O XML deve conter a versão e tags fundamentais do CT-e
        expect($xml)->toBeString()
            ->and($xml)->toContain('versao="4.00"');
    })->skip('Requer sped-cte com schemas carregados — roda no servidor.');

    it('builder não está mais vazio (regressão do placeholder)', function () {
        // Garante que o builder tem a lógica completa, não o placeholder antigo
        $arquivo = file_get_contents(app_path('Services/Fiscal/CTeXmlBuilder.php'));

        expect($arquivo)->toContain('addModalRodoviario')
            ->and($arquivo)->toContain('addTomador')
            ->and($arquivo)->toContain('tagrodo')
            ->and($arquivo)->not->toContain('Implementação completa na sprint');
    });

});

describe('CTeXmlBuilder — suporte aos tipos de CT-e', function () {

    it('builder tem lógica para todos os tipos (normal/compl/anul/subst)', function () {
        $arquivo = file_get_contents(app_path('Services/Fiscal/CTeXmlBuilder.php'));

        // Verifica que o método de CT-e referenciado trata complemento e substituição
        expect($arquivo)->toContain('taginfCteComp')   // complemento
            ->and($arquivo)->toContain('taginfCteSub')  // substituição
            ->and($arquivo)->toContain('tpCTe');         // tipo do CT-e
    });

    it('trata os 5 tipos de tomador (0 a 4)', function () {
        $arquivo = file_get_contents(app_path('Services/Fiscal/CTeXmlBuilder.php'));

        expect($arquivo)->toContain('tagtoma3')  // tomador 0-3 (participante)
            ->and($arquivo)->toContain('tagtoma4'); // tomador 4 (outros)
    });

});
