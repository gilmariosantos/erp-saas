<?php
namespace Database\Factories;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

class NfseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'empresa_id'       => Empresa::factory(),
            'ambiente'         => 2,
            'padrao_municipal' => 'abrasf',
            'codigo_municipio' => '3550308',
            'data_emissao'     => now(),
            'data_competencia' => now(),
            'descricao_servico'=> $this->faker->sentence(5),
            'codigo_servico'   => '1.01',
            'valor_servico'    => $this->faker->randomFloat(2, 100, 5000),
            'valor_liquido'    => $this->faker->randomFloat(2, 90, 4900),
            'aliquota_iss'     => 5.0,
            'iss_retido'       => false,
            'status'           => 'rascunho',
        ];
    }
    public function autorizada(): static
    {
        return $this->state([
            'status'              => 'autorizada',
            'numero'              => $this->faker->numerify('#####'),
            'numero_verificacao'  => $this->faker->numerify('##########'),
        ]);
    }
}
