<?php

namespace Database\Factories;

use App\Enums\NFeStatus;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

class NfeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'empresa_id'              => Empresa::factory(),
            'numero'                  => $this->faker->unique()->numerify('#########'),
            'serie'                   => '1',
            'modelo'                  => '55',
            'ambiente'                => 2,
            'tipo_emissao'            => '1',
            'finalidade'              => '1',
            'operacao'                => '1',
            'natureza_operacao'       => 'VENDA DE MERCADORIA',
            'data_emissao'            => now(),
            'emitente_cnpj'           => $this->faker->numerify('##############'),
            'emitente_razao_social'   => $this->faker->company(),
            'emitente_uf'             => 'SP',
            'destinatario_cnpj_cpf'   => $this->faker->numerify('##############'),
            'destinatario_nome'       => $this->faker->company(),
            'destinatario_uf'         => 'SP',
            'destinatario_indicador_ie' => 9,
            'total_nota'              => 1000.00,
            'modalidade_frete'        => '9',
            'status'                  => NFeStatus::RASCUNHO,
            'tentativas_envio'        => 0,
        ];
    }

    public function rascunho(): static  { return $this->state(['status' => NFeStatus::RASCUNHO]); }
    public function autorizada(): static
    {
        return $this->state([
            'status'                 => NFeStatus::AUTORIZADA,
            'chave_acesso'           => str_repeat('1', 44),
            'protocolo_autorizacao'  => '141240000000001',
            'data_autorizacao'       => now(),
            'xml_retorno'            => '<nfeProc/>',
            'cce_sequencia'          => 0,
        ]);
    }
    public function cancelada(): static { return $this->state(['status' => NFeStatus::CANCELADA]); }
    public function rejeitada(): static { return $this->state(['status' => NFeStatus::REJEITADA]); }
}
