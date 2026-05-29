<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EtapaFunil extends Model
{
    protected $table = 'etapas_funil';
    protected $fillable = ['funil_id','nome','cor','ordem','probabilidade','is_won','is_lost'];
    protected function casts(): array { return ['is_won'=>'boolean','is_lost'=>'boolean','probabilidade'=>'decimal:2']; }
    public function funil()        { return $this->belongsTo(FunilVenda::class, 'funil_id'); }
    public function oportunidades(){ return $this->hasMany(Oportunidade::class, 'etapa_id'); }
}
