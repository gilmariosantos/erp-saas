<?php

namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedidoCompra extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $fillable = [
        'empresa_id','numero','fornecedor_id','local_estoque_id',
        'data_pedido','data_previsao_entrega','data_recebimento','status',
        'total_produtos','total_frete','total_desconto','total_pedido',
        'observacao','numero_cotacao','condicao_pagamento','created_by',
    ];

    protected function casts(): array
    {
        return [
            'data_pedido'            => 'date',
            'data_previsao_entrega'  => 'date',
            'data_recebimento'       => 'date',
            'total_produtos'         => 'decimal:2',
            'total_frete'            => 'decimal:2',
            'total_desconto'         => 'decimal:2',
            'total_pedido'           => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo     { return $this->belongsTo(Empresa::class); }
    public function fornecedor(): BelongsTo  { return $this->belongsTo(Pessoa::class, 'fornecedor_id'); }
    public function localEstoque(): BelongsTo{ return $this->belongsTo(LocalEstoque::class); }
    public function itens(): HasMany         { return $this->hasMany(PedidoCompraItem::class); }

    public function podeReceber(): bool
    {
        return in_array($this->status, ['confirmado', 'parcial']);
    }

    public function percentualRecebido(): float
    {
        $total    = $this->itens->sum('quantidade');
        $recebido = $this->itens->sum('quantidade_recebida');
        return $total > 0 ? round(($recebido / $total) * 100, 1) : 0;
    }
}
