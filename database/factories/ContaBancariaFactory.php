<?php

namespace Database\Factories;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContaBancariaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'empresa_id'       => Empresa::factory(),
            'nome'             => $this->faker->randomElement(['Caixa Geral', 'Bradesco PJ', 'Itaú PJ', 'Nubank PJ']),
            'tipo'             => $this->faker->randomElement(['corrente', 'caixa']),
            'banco_codigo'     => $this->faker->numerify('###'),
            'banco_nome'       => $this->faker->company(),
            'agencia'          => $this->faker->numerify('####'),
            'conta'            => $this->faker->numerify('#####'),
            'conta_digito'     => $this->faker->numerify('#'),
            'saldo_inicial'    => 0,
            'saldo_atual'      => 0,
            'is_active'        => true,
            'exibir_dashboard' => true,
        ];
    }

    public function comSaldo(float $saldo): static
    {
        return $this->state(['saldo_atual' => $saldo, 'saldo_inicial' => $saldo]);
    }
}
