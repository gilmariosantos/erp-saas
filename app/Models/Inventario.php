<?php

namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventario extends Model
{
    use HasFactory, SoftDeletes, HasAudit;
    protected $fillable = [
        'empresa_id','local_estoque_id','descricao','data_inventario',
        'status','iniciado_em','finalizado_em','created_by',
    ];
    protected function casts(): array
    {
        return [
            'data_inventario' => 'date',
            'iniciado_em'     => 'datetime',
            'finalizado_em'   => 'datetime',
        ];
    }
    public function empresa()     { return $this->belongsTo(Empresa::class); }
    public function local()       { return $this->belongsTo(LocalEstoque::class, 'local_estoque_id'); }
    public function itens(): HasMany { return $this->hasMany(InventarioItem::class); }
}
