<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtratoBancario extends Model
{
    use HasFactory;

    protected $table = 'extrato_bancario';

    protected $fillable = [
        'conta_bancaria_id','data_movimento','tipo','valor','saldo_apos',
        'descricao','documento','origem_tipo','origem_id',
        'conciliado','conciliado_em','conciliado_por',
    ];

    protected function casts(): array
    {
        return [
            'data_movimento' => 'date',
            'conciliado_em'  => 'datetime',
            'valor'          => 'decimal:2',
            'saldo_apos'     => 'decimal:2',
            'conciliado'     => 'boolean',
        ];
    }

    public function conta()        { return $this->belongsTo(ContaBancaria::class, 'conta_bancaria_id'); }
    public function conciliadoPor(){ return $this->belongsTo(User::class, 'conciliado_por'); }
}
