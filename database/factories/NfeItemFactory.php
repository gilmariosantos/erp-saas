<?php

namespace Database\Factories;

use App\Models\Nfe;
use App\Models\Produto;
use Illuminate\Database\Eloquent\Factories\Factory;

class NfeItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nfe_id'        => Nfe::factory(),
            'numero_item'   => 1,
            'descricao'     => $this->faker->words(2, true),
            'cfop'          => '5102',
            'unidade'       => 'UN',
            'quantidade'    => $this->faker->randomFloat(2, 1, 100),
            'valor_unitario'=> $this->faker->randomFloat(4, 10, 500),
            'valor_bruto'   => 100.00,
            'valor_total'   => 100.00,
            'origem'        => '0',
            'compoe_total'  => true,
            'cst_icms'      => '102',
            'cst_pis'       => '07',
            'cst_cofins'    => '07',
        ];
    }
}
