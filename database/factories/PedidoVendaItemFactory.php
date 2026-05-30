<?php
namespace Database\Factories;
use App\Models\PedidoVenda;
use App\Models\Produto;
use Illuminate\Database\Eloquent\Factories\Factory;

class PedidoVendaItemFactory extends Factory
{
    public function definition(): array
    {
        $qtd = $this->faker->randomFloat(2, 1, 20);
        $preco = $this->faker->randomFloat(4, 10, 500);
        return [
            'pedido_venda_id'     => PedidoVenda::factory(),
            'produto_id'          => Produto::factory(),
            'numero_item'         => 1,
            'descricao'           => $this->faker->words(2, true),
            'quantidade'          => $qtd,
            'preco_unitario'      => $preco,
            'desconto_percentual' => 0,
            'desconto_valor'      => 0,
            'total'               => round($qtd * $preco, 2),
            'custo_unitario'      => $preco * 0.6,
            'margem'              => 40.0,
        ];
    }
}
