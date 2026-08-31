<?php
namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $planos = [
            [
                'name' => 'Início', 'slug' => 'inicio',
                'description' => 'Para quem está começando e emite poucas notas.',
                'price_monthly' => 79.90, 'price_annual' => 799.00,
                'max_users' => 2, 'max_empresas' => 1,
                'max_nfe_mes' => 50, 'max_cte_mes' => 10, 'storage_gb' => 5,
                'features' => ['NF-e e NFC-e', 'Financeiro', 'Estoque', 'Suporte por e-mail'],
                'sort_order' => 1,
            ],
            [
                'name' => 'Pro', 'slug' => 'pro',
                'description' => 'Para empresas em crescimento que precisam de mais volume.',
                'price_monthly' => 149.90, 'price_annual' => 1499.00,
                'max_users' => 10, 'max_empresas' => 3,
                'max_nfe_mes' => 500, 'max_cte_mes' => 100, 'storage_gb' => 20,
                'features' => ['Tudo do Início', 'CT-e e CIOT', 'Ordem de Serviço', 'Múltiplas empresas', 'Suporte prioritário'],
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise', 'slug' => 'enterprise',
                'description' => 'Volume ilimitado e recursos avançados.',
                'price_monthly' => 399.90, 'price_annual' => 3999.00,
                'max_users' => 9999, 'max_empresas' => 9999,
                'max_nfe_mes' => 9999, 'max_cte_mes' => 9999, 'storage_gb' => 200,
                'features' => ['Tudo do Pro', 'NF-e/CT-e ilimitados', 'Usuários ilimitados', 'API dedicada', 'Suporte 24/7', 'Gerente de conta'],
                'sort_order' => 3,
            ],
        ];

        foreach ($planos as $p) {
            Plan::updateOrCreate(['slug' => $p['slug']], $p);
        }
    }
}
