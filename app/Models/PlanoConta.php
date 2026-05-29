<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanoConta extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'plano_contas';
    protected $fillable = ['codigo','nome','tipo','natureza','parent_id','nivel','aceita_lancamento','is_active','sort_order'];
    protected function casts(): array { return ['aceita_lancamento' => 'boolean','is_active' => 'boolean']; }
    public function parent()    { return $this->belongsTo(self::class, 'parent_id'); }
    public function filhos()    { return $this->hasMany(self::class, 'parent_id'); }
    public function lancamentos(){ return $this->hasMany(Lancamento::class, 'plano_conta_id'); }
}
