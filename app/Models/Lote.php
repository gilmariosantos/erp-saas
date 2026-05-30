<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lote extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'produto_id','numero','data_fabricacao','data_validade',
        'fornecedor_lote','quantidade_inicial','quantidade_atual','is_active',
    ];
    protected function casts(): array
    {
        return [
            'data_fabricacao'    => 'date',
            'data_validade'      => 'date',
            'quantidade_inicial' => 'decimal:4',
            'quantidade_atual'   => 'decimal:4',
            'is_active'          => 'boolean',
        ];
    }
    public function produto() { return $this->belongsTo(Produto::class); }
    public function isVencido(): bool { return $this->data_validade && $this->data_validade->isPast(); }
    public function diasParaVencer(): ?int
    {
        return $this->data_validade ? (int) now()->diffInDays($this->data_validade, false) : null;
    }
}
