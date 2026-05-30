<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UnidadesMedidaSeeder::class,
            PlanoContasSeeder::class,
        ]);
    }
}
