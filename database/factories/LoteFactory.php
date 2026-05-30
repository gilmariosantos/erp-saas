<?php

namespace Database\Factories;

use App\Models\Produto;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'produto_id'         => Produto::factory(),
            'numero'             => $this->faker->unique()->bothify('LOTE-####'),
            'data_fabricacao'    => today()->subMonths(3)->toDateString(),
            'data_validade'      => today()->addYear()->toDateString(),
            'quantidade_inicial' => 100.0,
            'quantidade_atual'   => 100.0,
            'is_active'          => true,
        ];
    }

    public function vencido(): static
    {
        return $this->state(['data_validade' => today()->subDay()->toDateString()]);
    }

    public function vencendoEm(int $dias): static
    {
        return $this->state(['data_validade' => today()->addDays($dias)->toDateString()]);
    }
}
