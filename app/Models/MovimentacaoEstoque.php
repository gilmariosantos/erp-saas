<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimentacaoEstoque extends Model
{
    use HasFactory;
    protected $table = 'movimentacoes_estoque';

    protected $fillable = [
        'empresa_id','produto_id','local_estoque_id','lote_id',
        'tipo','origem_tipo','origem_id','origem_descricao',
        'quantidade','custo_unitario',
        'saldo_anterior','saldo_posterior',
        'data_movimento','observacao','created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantidade'      => 'decimal:4',
            'custo_unitario'  => 'decimal:4',
            'saldo_anterior'  => 'decimal:4',
            'saldo_posterior' => 'decimal:4',
            'data_movimento'  => 'date',
        ];
    }

    public function produto(): BelongsTo      { return $this->belongsTo(Produto::class); }
    public function localEstoque(): BelongsTo { return $this->belongsTo(LocalEstoque::class); }
    public function lote(): BelongsTo         { return $this->belongsTo(Lote::class); }
    public function isEntrada(): bool         { return $this->tipo === 'entrada'; }
    public function isSaida(): bool           { return $this->tipo === 'saida'; }
}
