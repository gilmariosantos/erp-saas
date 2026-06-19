<?php
namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Pessoa;
use Illuminate\Database\Eloquent\Factories\Factory;

class EquipamentoFactory extends Factory
{
    protected $model = \App\Models\Equipamento::class;
    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'cliente_id' => Pessoa::factory()->cliente(),
            'tipo'       => $this->faker->randomElement(['Notebook', 'Impressora', 'Veículo']),
            'marca'      => $this->faker->company(),
            'modelo'     => $this->faker->bothify('Modelo-###'),
            'is_active'  => true,
        ];
    }
}
