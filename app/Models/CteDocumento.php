<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CteDocumento extends Model
{
    use HasFactory;
    protected $fillable = [
        'cte_id','tipo','chave_nfe','numero','serie','subserie',
        'data_emissao','cnpj_emitente','valor',
        'peso_bruto','peso_liquido','quantidade_volumes','unidade_volumes',
    ];
    protected function casts(): array { return ['data_emissao' => 'date','valor' => 'decimal:2']; }
    public function cte() { return $this->belongsTo(Cte::class); }
}
