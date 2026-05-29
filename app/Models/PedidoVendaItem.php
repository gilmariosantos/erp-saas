<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PedidoVendaItem extends Model
{
    use HasFactory;
    protected $table = 'pedido_venda_itens';
    protected $fillable = [
        'pedido_venda_id','produto_id','numero_item','descricao',
        'quantidade','preco_unitario','desconto_percentual','desconto_valor',
        'total','custo_unitario','margem','observacao',
    ];
    protected function casts(): array
    {
        return [
            'quantidade'     => 'decimal:4',
            'preco_unitario' => 'decimal:4',
            'total'          => 'decimal:2',
            'custo_unitario' => 'decimal:4',
            'margem'         => 'decimal:4',
        ];
    }
    public function pedido()  { return $this->belongsTo(PedidoVenda::class, 'pedido_venda_id'); }
    public function produto() { return $this->belongsTo(Produto::class); }
}
