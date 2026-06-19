<?php
namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Pessoa;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrdemServicoFactory extends Factory
{
    protected $model = \App\Models\OrdemServico::class;
    public function definition(): array
    {
        return [
            'empresa_id'    => Empresa::factory(),
            'numero'        => 'OS-' . $this->faker->unique()->numerify('####'),
            'cliente_id'    => Pessoa::factory()->cliente(),
            'data_abertura' => today()->toDateString(),
            'situacao'      => 'orcamento',
            'total_geral'   => 0,
            'garantia_dias' => 90,
        ];
    }
    public function aprovada(): static { return $this->state(['situacao' => 'aprovada']); }
    public function concluida(): static { return $this->state(['situacao' => 'concluida']); }
}
