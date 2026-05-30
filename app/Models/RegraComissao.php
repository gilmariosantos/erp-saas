<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RegraComissao extends Model
{
    protected $table = 'regras_comissao';
    protected $fillable = ['empresa_id','nome','vendedor_id','percentual','base_calculo','meta_minima','is_active'];
    protected function casts(): array { return ['percentual'=>'decimal:4','meta_minima'=>'decimal:2','is_active'=>'boolean']; }
    public function vendedor() { return $this->belongsTo(User::class, 'vendedor_id'); }
}
