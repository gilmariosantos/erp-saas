<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventarioItem extends Model
{
    use HasFactory;
    protected $table = 'inventario_itens';
    protected $fillable = [
        'inventario_id','produto_id','lote_id',
        'quantidade_sistema','quantidade_contada',
        'custo_unitario','contado','observacao',
    ];
    protected function casts(): array
    {
        return [
            'quantidade_sistema' => 'decimal:4',
            'quantidade_contada' => 'decimal:4',
            'custo_unitario'     => 'decimal:4',
            'contado'            => 'boolean',
        ];
    }
    public function inventario() { return $this->belongsTo(Inventario::class); }
    public function produto()    { return $this->belongsTo(Produto::class); }
    public function lote()       { return $this->belongsTo(Lote::class); }
}
