<?php

namespace Database\Factories;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocalEstoqueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'nome'       => $this->faker->randomElement(['Depósito Principal', 'Almoxarifado', 'Loja']),
            'is_padrao'  => false,
            'is_active'  => true,
        ];
    }

    public function padrao(): static { return $this->state(['is_padrao' => true]); }
}
