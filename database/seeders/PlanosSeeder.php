<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanosSeeder extends Seeder
{
    public function run(): void
    {
        $planos = [
            [
                'name'          => 'Básico',
                'slug'          => 'basico',
                'description'   => 'Ideal para MEI e pequenas empresas',
                'price_monthly' => 99.90,
                'price_annual'  => 999.00,
                'max_users'     => 3,
                'max_empresas'  => 1,
                'max_nfe_mes'   => 100,
                'max_cte_mes'   => 20,
                'storage_gb'    => 5,
                'features'      => json_encode(['nfe', 'financeiro', 'estoque_basico']),
                'is_active'     => true,
                'sort_order'    => 1,
            ],
            [
                'name'          => 'Pro',
                'slug'          => 'pro',
                'description'   => 'Para empresas em crescimento',
                'price_monthly' => 249.90,
                'price_annual'  => 2499.00,
                'max_users'     => 10,
                'max_empresas'  => 3,
                'max_nfe_mes'   => 500,
                'max_cte_mes'   => 100,
                'storage_gb'    => 20,
                'features'      => json_encode(['nfe', 'nfce', 'cte', 'ciot', 'financeiro', 'estoque', 'crm']),
                'is_active'     => true,
                'sort_order'    => 2,
            ],
            [
                'name'          => 'Enterprise',
                'slug'          => 'enterprise',
                'description'   => 'Ilimitado para grandes operações',
                'price_monthly' => 599.90,
                'price_annual'  => 5999.00,
                'max_users'     => 9999,
                'max_empresas'  => 9999,
                'max_nfe_mes'   => 9999,
                'max_cte_mes'   => 9999,
                'storage_gb'    => 100,
                'features'      => json_encode(['nfe', 'nfce', 'nfse', 'cte', 'ciot', 'mdfe', 'financeiro', 'estoque', 'crm', 'bi', 'api']),
                'is_active'     => true,
                'sort_order'    => 3,
            ],
        ];

        foreach ($planos as $plano) {
            DB::table('plans')->updateOrInsert(['slug' => $plano['slug']], $plano);
        }
    }
}
