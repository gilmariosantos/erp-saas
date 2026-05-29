<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transferencia extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'empresa_id','conta_origem_id','conta_destino_id',
        'data_transferencia','valor','descricao','observacao','created_by',
    ];

    protected function casts(): array
    {
        return ['data_transferencia' => 'date', 'valor' => 'decimal:2'];
    }

    public function empresa()      { return $this->belongsTo(Empresa::class); }
    public function contaOrigem()  { return $this->belongsTo(ContaBancaria::class, 'conta_origem_id'); }
    public function contaDestino() { return $this->belongsTo(ContaBancaria::class, 'conta_destino_id'); }
}
