<?php

namespace Database\Factories;

use App\Enums\LancamentoStatus;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

class LancamentoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'empresa_id'     => 1,
            'tipo'           => $this->faker->randomElement(['pagar', 'receber']),
            'descricao'      => $this->faker->sentence(3),
            'data_emissao'   => today()->toDateString(),
            'data_vencimento'=> today()->addDays(30)->toDateString(),
            'valor_original' => $this->faker->randomFloat(2, 100, 5000),
            'valor_pago'     => 0,
            'valor_juros'    => 0,
            'valor_multa'    => 0,
            'valor_desconto' => 0,
            'status'         => LancamentoStatus::ABERTO,
            'parcela_numero' => 1,
            'parcela_total'  => 1,
        ];
    }

    public function pagar(): static   { return $this->state(['tipo' => 'pagar']); }
    public function receber(): static { return $this->state(['tipo' => 'receber']); }
    public function vencido(): static
    {
        return $this->state([
            'data_vencimento' => today()->subDays(10)->toDateString(),
            'status'          => LancamentoStatus::VENCIDO,
        ]);
    }
    public function pago(): static
    {
        return $this->afterMaking(function ($l) {
            $l->valor_pago  = $l->valor_original;
            $l->status      = LancamentoStatus::PAGO;
            $l->data_pagamento = today()->toDateString();
        });
    }
}
