<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoCompraItem extends Model
{
    use HasFactory;
    protected $table = 'pedido_compra_itens';
    protected $fillable = [
        'pedido_compra_id','produto_id','numero_item',
        'quantidade','quantidade_recebida','preco_unitario',
        'desconto','total','data_entrega_prevista','observacao',
    ];
    protected function casts(): array
    {
        return [
            'quantidade'          => 'decimal:4',
            'quantidade_recebida' => 'decimal:4',
            'preco_unitario'      => 'decimal:4',
            'total'               => 'decimal:2',
            'data_entrega_prevista'=> 'date',
        ];
    }
    public function pedido(): BelongsTo  { return $this->belongsTo(PedidoCompra::class, 'pedido_compra_id'); }
    public function produto(): BelongsTo { return $this->belongsTo(Produto::class); }
    public function quantidadePendente(): float
    {
        return max(0, $this->quantidade - $this->quantidade_recebida);
    }
}
