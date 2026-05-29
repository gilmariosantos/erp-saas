<?php

namespace Database\Seeders;

use App\Models\UnidadeMedida;
use Illuminate\Database\Seeder;

class UnidadesMedidaSeeder extends Seeder
{
    public function run(): void
    {
        $unidades = [
            ['sigla' => 'UN',  'descricao' => 'Unidade'],
            ['sigla' => 'CX',  'descricao' => 'Caixa'],
            ['sigla' => 'PC',  'descricao' => 'Peça'],
            ['sigla' => 'KG',  'descricao' => 'Quilograma'],
            ['sigla' => 'G',   'descricao' => 'Grama'],
            ['sigla' => 'L',   'descricao' => 'Litro'],
            ['sigla' => 'ML',  'descricao' => 'Mililitro'],
            ['sigla' => 'M',   'descricao' => 'Metro'],
            ['sigla' => 'M2',  'descricao' => 'Metro Quadrado'],
            ['sigla' => 'M3',  'descricao' => 'Metro Cúbico'],
            ['sigla' => 'CM',  'descricao' => 'Centímetro'],
            ['sigla' => 'TON', 'descricao' => 'Tonelada'],
            ['sigla' => 'PAR', 'descricao' => 'Par'],
            ['sigla' => 'DZ',  'descricao' => 'Dúzia'],
            ['sigla' => 'PCT', 'descricao' => 'Pacote'],
            ['sigla' => 'RL',  'descricao' => 'Rolo'],
            ['sigla' => 'FD',  'descricao' => 'Fardo'],
            ['sigla' => 'SC',  'descricao' => 'Saco'],
            ['sigla' => 'H',   'descricao' => 'Hora'],
            ['sigla' => 'SV',  'descricao' => 'Serviço'],
        ];

        foreach ($unidades as $u) {
            UnidadeMedida::firstOrCreate(['sigla' => $u['sigla']], $u);
        }
    }
}
