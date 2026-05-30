<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EmpresaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'razao_social'        => $this->faker->company(),
            'nome_fantasia'       => $this->faker->company(),
            'cnpj'                => $this->faker->numerify('##.###.###/####-##'),
            'tipo_pessoa'         => 'PJ',
            'regime_tributario'   => '1',
            'logradouro'          => $this->faker->streetName(),
            'numero'              => $this->faker->buildingNumber(),
            'municipio'           => 'São Paulo',
            'codigo_municipio'    => '3550308',
            'uf'                  => 'SP',
            'cep'                 => '01310-100',
            'telefone'            => $this->faker->phoneNumber(),
            'email'               => $this->faker->companyEmail(),
            'serie_nfe'           => 1,
            'numero_nfe'          => 0,
            'ambiente_nfe'        => 2,
            'serie_cte'           => 1,
            'numero_cte'          => 0,
            'ambiente_cte'        => 2,
            'is_active'           => true,
            'is_matriz'           => true,
        ];
    }

    public function comCertificado(): static
    {
        return $this->state([
            'certificado_path'    => 'certs/test.pfx',
            'certificado_senha'   => encrypt('senha123'),
            'certificado_validade'=> now()->addYear(),
            'rntrc'               => '01234567',
        ]);
    }
}
