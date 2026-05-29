<?php

namespace Database\Seeders;

use App\Models\PlanoConta;
use Illuminate\Database\Seeder;

class PlanoContasSeeder extends Seeder
{
    public function run(): void
    {
        $plano = [
            // Receitas
            ['codigo' => '1',     'nome' => 'RECEITAS',                         'tipo' => 'receita', 'nivel' => 1, 'aceita_lancamento' => false],
            ['codigo' => '1.1',   'nome' => 'Receitas Operacionais',             'tipo' => 'receita', 'nivel' => 2, 'aceita_lancamento' => false, 'parent' => '1'],
            ['codigo' => '1.1.01','nome' => 'Vendas de Produtos',                'tipo' => 'receita', 'nivel' => 3, 'parent' => '1.1'],
            ['codigo' => '1.1.02','nome' => 'Prestação de Serviços',             'tipo' => 'receita', 'nivel' => 3, 'parent' => '1.1'],
            ['codigo' => '1.1.03','nome' => 'Receitas de Fretes',                'tipo' => 'receita', 'nivel' => 3, 'parent' => '1.1'],
            ['codigo' => '1.2',   'nome' => 'Receitas Financeiras',              'tipo' => 'receita', 'nivel' => 2, 'aceita_lancamento' => false, 'parent' => '1'],
            ['codigo' => '1.2.01','nome' => 'Juros Recebidos',                   'tipo' => 'receita', 'nivel' => 3, 'parent' => '1.2'],
            ['codigo' => '1.2.02','nome' => 'Descontos Obtidos',                 'tipo' => 'receita', 'nivel' => 3, 'parent' => '1.2'],
            // Despesas
            ['codigo' => '2',     'nome' => 'DESPESAS',                          'tipo' => 'despesa', 'nivel' => 1, 'aceita_lancamento' => false],
            ['codigo' => '2.1',   'nome' => 'Despesas Operacionais',             'tipo' => 'despesa', 'nivel' => 2, 'aceita_lancamento' => false, 'parent' => '2'],
            ['codigo' => '2.1.01','nome' => 'Fornecedores / Compras',            'tipo' => 'despesa', 'nivel' => 3, 'parent' => '2.1'],
            ['codigo' => '2.1.02','nome' => 'Folha de Pagamento',                'tipo' => 'despesa', 'nivel' => 3, 'parent' => '2.1'],
            ['codigo' => '2.1.03','nome' => 'Aluguel',                           'tipo' => 'despesa', 'nivel' => 3, 'parent' => '2.1'],
            ['codigo' => '2.1.04','nome' => 'Energia Elétrica',                  'tipo' => 'despesa', 'nivel' => 3, 'parent' => '2.1'],
            ['codigo' => '2.1.05','nome' => 'Telefone / Internet',               'tipo' => 'despesa', 'nivel' => 3, 'parent' => '2.1'],
            ['codigo' => '2.1.06','nome' => 'Combustível / Manutenção Veículos', 'tipo' => 'despesa', 'nivel' => 3, 'parent' => '2.1'],
            ['codigo' => '2.1.07','nome' => 'Fretes e Carretos (Compras)',       'tipo' => 'despesa', 'nivel' => 3, 'parent' => '2.1'],
            ['codigo' => '2.1.08','nome' => 'Impostos e Taxas',                  'tipo' => 'despesa', 'nivel' => 3, 'parent' => '2.1'],
            ['codigo' => '2.2',   'nome' => 'Despesas Financeiras',              'tipo' => 'despesa', 'nivel' => 2, 'aceita_lancamento' => false, 'parent' => '2'],
            ['codigo' => '2.2.01','nome' => 'Juros Pagos',                       'tipo' => 'despesa', 'nivel' => 3, 'parent' => '2.2'],
            ['codigo' => '2.2.02','nome' => 'Tarifas Bancárias',                 'tipo' => 'despesa', 'nivel' => 3, 'parent' => '2.2'],
            ['codigo' => '2.2.03','nome' => 'IOF',                               'tipo' => 'despesa', 'nivel' => 3, 'parent' => '2.2'],
        ];

        $created = [];
        foreach ($plano as $item) {
            $parentId = null;
            if (isset($item['parent'])) {
                $parentId = $created[$item['parent']] ?? null;
            }
            $conta = PlanoConta::firstOrCreate(
                ['codigo' => $item['codigo']],
                [
                    'nome'              => $item['nome'],
                    'tipo'              => $item['tipo'],
                    'natureza'          => $item['tipo'] === 'receita' ? 'credora' : 'devedora',
                    'nivel'             => $item['nivel'],
                    'parent_id'         => $parentId,
                    'aceita_lancamento' => $item['aceita_lancamento'] ?? true,
                    'is_active'         => true,
                ]
            );
            $created[$item['codigo']] = $conta->id;
        }
    }
}
