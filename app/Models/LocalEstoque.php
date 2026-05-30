<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocalEstoque extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'locais_estoque';
    protected $fillable = ['empresa_id','nome','descricao','is_padrao','is_active'];
    protected function casts(): array { return ['is_padrao' => 'boolean','is_active' => 'boolean']; }
    public function empresa()       { return $this->belongsTo(Empresa::class); }
    public function movimentacoes() { return $this->hasMany(MovimentacaoEstoque::class); }
}
