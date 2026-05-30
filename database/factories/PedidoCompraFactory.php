<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Pessoa;
use Illuminate\Database\Eloquent\Factories\Factory;

class PedidoCompraFactory extends Factory
{
    public function definition(): array
    {
        return [
            'empresa_id'   => Empresa::factory(),
            'fornecedor_id'=> Pessoa::factory()->fornecedor(),
            'data_pedido'  => today()->toDateString(),
            'status'       => 'rascunho',
            'total_pedido' => 0,
        ];
    }

    public function confirmado(): static { return $this->state(['status' => 'confirmado']); }
    public function recebido(): static   { return $this->state(['status' => 'recebido']); }
}
