<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Comissao extends Model
{
    protected $fillable = ['pedido_venda_id','vendedor_id','regra_id','valor_base','percentual','valor_comissao','status','paga_em'];
    protected function casts(): array
    {
        return ['valor_base'=>'decimal:2','percentual'=>'decimal:4','valor_comissao'=>'decimal:2','paga_em'=>'date'];
    }
    public function pedido()  { return $this->belongsTo(PedidoVenda::class, 'pedido_venda_id'); }
    public function vendedor(){ return $this->belongsTo(User::class, 'vendedor_id'); }
}
