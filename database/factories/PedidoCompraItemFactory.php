<?php

namespace Database\Factories;

use App\Models\PedidoCompra;
use App\Models\Produto;
use Illuminate\Database\Eloquent\Factories\Factory;

class PedidoCompraItemFactory extends Factory
{
    public function definition(): array
    {
        $qtd   = $this->faker->randomFloat(2, 1, 50);
        $preco = $this->faker->randomFloat(4, 10, 500);
        return [
            'pedido_compra_id'    => PedidoCompra::factory(),
            'produto_id'          => Produto::factory(),
            'numero_item'         => 1,
            'quantidade'          => $qtd,
            'quantidade_recebida' => 0,
            'preco_unitario'      => $preco,
            'desconto'            => 0,
            'total'               => round($qtd * $preco, 2),
        ];
    }
}
