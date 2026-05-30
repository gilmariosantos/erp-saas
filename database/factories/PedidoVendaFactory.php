<?php
namespace Database\Factories;
use App\Models\Empresa;
use App\Models\Pessoa;
use Illuminate\Database\Eloquent\Factories\Factory;

class PedidoVendaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'empresa_id'  => Empresa::factory(),
            'cliente_id'  => Pessoa::factory()->cliente(),
            'tipo'        => 'pedido',
            'data_pedido' => today()->toDateString(),
            'status'      => 'rascunho',
            'total_pedido'=> 0,
        ];
    }
    public function orcamento(): static { return $this->state(['tipo' => 'orcamento', 'data_validade' => today()->addDays(30)->toDateString()]); }
    public function aprovado(): static  { return $this->state(['status' => 'aprovado']); }
    public function faturado(): static  { return $this->state(['status' => 'faturado']); }
}
