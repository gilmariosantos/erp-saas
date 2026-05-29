<?php

namespace Database\Factories;

use App\Models\ContaBancaria;
use App\Models\Lancamento;
use Illuminate\Database\Eloquent\Factories\Factory;

class LancamentoBaixaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lancamento_id'     => Lancamento::factory(),
            'conta_bancaria_id' => ContaBancaria::factory(),
            'data_pagamento'    => today()->toDateString(),
            'valor_pago'        => $this->faker->randomFloat(2, 100, 1000),
            'valor_juros'       => 0,
            'valor_multa'       => 0,
            'valor_desconto'    => 0,
        ];
    }
}
