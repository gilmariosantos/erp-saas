<?php

namespace Database\Factories;

use App\Enums\CTeStatus;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

class CteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'empresa_id'            => Empresa::factory()->comCertificado(),
            'numero'                => $this->faker->unique()->numerify('#########'),
            'serie'                 => '1',
            'modelo'                => '57',
            'ambiente'              => 2,
            'tipo_emissao'          => '1',
            'tipo_ct'               => '0',
            'tipo_servico'          => '0',
            'modal'                 => '01',
            'data_emissao'          => now(),
            'natureza_operacao'     => 'PRESTAÇÃO DE SERVIÇO DE TRANSPORTE',
            'cfop'                  => '5353',
            'emitente_cnpj'         => $this->faker->numerify('##############'),
            'emitente_razao_social' => $this->faker->company(),
            'emitente_uf'           => 'SP',
            'emitente_rntrc'        => '01234567',
            'municipio_inicio'      => 'São Paulo',
            'uf_inicio'             => 'SP',
            'municipio_fim'         => 'Rio de Janeiro',
            'uf_fim'                => 'RJ',
            'valor_total_servico'   => 500.00,
            'valor_carga'           => 5000.00,
            'status'                => CTeStatus::RASCUNHO,
            'tentativas_envio'      => 0,
        ];
    }

    public function rascunho(): static  { return $this->state(['status' => CTeStatus::RASCUNHO]); }
    public function autorizada(): static
    {
        return $this->state([
            'status'                => CTeStatus::AUTORIZADA,
            'chave_acesso'          => str_repeat('9', 44),
            'protocolo_autorizacao' => '341240000000001',
            'data_autorizacao'      => now(),
            'xml_retorno'           => '<cteProc/>',
        ]);
    }
}
