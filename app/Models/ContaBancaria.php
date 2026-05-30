<?php

namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContaBancaria extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'empresa_id','nome','tipo','banco_codigo','banco_nome',
        'agencia','agencia_digito','conta','conta_digito',
        'pix_chave','pix_tipo',
        'saldo_inicial','saldo_inicial_data','saldo_atual',
        'is_active','exibir_dashboard','cor',
    ];

    protected function casts(): array
    {
        return [
            'saldo_inicial'      => 'decimal:2',
            'saldo_atual'        => 'decimal:2',
            'saldo_inicial_data' => 'date',
            'is_active'          => 'boolean',
            'exibir_dashboard'   => 'boolean',
        ];
    }

    public function empresa()    { return $this->belongsTo(Empresa::class); }
    public function lancamentos(){ return $this->hasMany(Lancamento::class); }
    public function extrato()    { return $this->hasMany(ExtratoBancario::class); }
}
