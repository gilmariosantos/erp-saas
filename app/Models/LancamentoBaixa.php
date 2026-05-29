<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LancamentoBaixa extends Model
{
    use HasFactory;

    protected $table = 'lancamento_baixas';

    protected $fillable = [
        'lancamento_id','conta_bancaria_id','forma_pagamento_id',
        'data_pagamento','valor_pago','valor_juros','valor_multa',
        'valor_desconto','observacao','comprovante','created_by',
    ];

    protected function casts(): array
    {
        return [
            'data_pagamento' => 'date',
            'valor_pago'     => 'decimal:2',
            'valor_juros'    => 'decimal:2',
            'valor_multa'    => 'decimal:2',
            'valor_desconto' => 'decimal:2',
        ];
    }

    public function lancamento(): BelongsTo    { return $this->belongsTo(Lancamento::class); }
    public function contaBancaria(): BelongsTo { return $this->belongsTo(ContaBancaria::class); }
    public function formaPagamento(): BelongsTo{ return $this->belongsTo(FormaPagamento::class); }
}
