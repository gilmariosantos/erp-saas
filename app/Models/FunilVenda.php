<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FunilVenda extends Model
{
    use HasFactory;
    protected $table = 'funis_venda';
    protected $fillable = ['empresa_id','nome','is_padrao','is_active'];
    protected function casts(): array { return ['is_padrao'=>'boolean','is_active'=>'boolean']; }
    public function etapas()        { return $this->hasMany(EtapaFunil::class, 'funil_id')->orderBy('ordem'); }
    public function oportunidades() { return $this->hasMany(Oportunidade::class, 'funil_id'); }
}
