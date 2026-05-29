<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CteComponenteValor extends Model
{
    protected $table = 'cte_componentes_valor';
    protected $fillable = ['cte_id','nome','valor'];
    protected function casts(): array { return ['valor' => 'decimal:2']; }
    public function cte() { return $this->belongsTo(Cte::class); }
}
