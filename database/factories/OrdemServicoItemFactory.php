<?php
namespace Database\Factories;

use App\Models\OrdemServico;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrdemServicoItemFactory extends Factory
{
    protected $model = \App\Models\OrdemServicoItem::class;
    public function definition(): array
    {
        $qtd = $this->faker->numberBetween(1, 5);
        $valor = $this->faker->randomFloat(2, 50, 500);
        return [
            'ordem_servico_id' => OrdemServico::factory(),
            'tipo'             => $this->faker->randomElement(['servico', 'peca']),
            'descricao'        => $this->faker->words(3, true),
            'quantidade'       => $qtd,
            'valor_unitario'   => $valor,
            'desconto_valor'   => 0,
            'total'            => $qtd * $valor,
            'aprovado'         => true,
        ];
    }
    public function servico(): static { return $this->state(['tipo' => 'servico', 'produto_id' => null]); }
    public function peca(): static { return $this->state(['tipo' => 'peca']); }
}
