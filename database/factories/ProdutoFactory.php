<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProdutoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo'           => $this->faker->unique()->numerify('PROD-####'),
            'descricao'        => $this->faker->words(3, true),
            'tipo'             => 'P',
            'preco_custo'      => $this->faker->randomFloat(2, 10, 500),
            'preco_venda'      => $this->faker->randomFloat(2, 20, 1000),
            'controla_estoque' => true,
            'estoque_atual'    => $this->faker->randomFloat(2, 0, 100),
            'estoque_minimo'   => 5.0,
            'estoque_maximo'   => 200.0,
            'ncm'              => $this->faker->numerify('########'),
            'cfop'             => '5102',
            'origem'           => '0',
            'is_active'        => true,
        ];
    }

    public function servico(): static
    {
        return $this->state([
            'tipo'             => 'S',
            'controla_estoque' => false,
            'estoque_atual'    => 0,
        ]);
    }

    public function semEstoque(): static
    {
        return $this->state(['estoque_atual' => 0.0]);
    }
}
