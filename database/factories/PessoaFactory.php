<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PessoaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome'        => $this->faker->company(),
            'tipo_pessoa' => 'PJ',
            'cnpj'        => $this->faker->numerify('##.###.###/####-##'),
            'is_cliente'  => true,
            'municipio'   => 'São Paulo',
            'uf'          => 'SP',
            'cep'         => '01310-100',
            'email'       => $this->faker->companyEmail(),
            'is_active'   => true,
        ];
    }

    public function cliente(): static   { return $this->state(['is_cliente' => true, 'is_fornecedor' => false]); }
    public function fornecedor(): static{ return $this->state(['is_fornecedor' => true]); }
    public function transportadora(): static { return $this->state(['is_transportadora' => true]); }
}
