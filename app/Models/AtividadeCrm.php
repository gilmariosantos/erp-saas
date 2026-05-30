<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AtividadeCrm extends Model
{
    protected $table = 'atividades_crm';
    protected $fillable = ['oportunidade_id','user_id','tipo','titulo','descricao','data_hora','concluida','concluida_em'];
    protected function casts(): array
    {
        return ['data_hora'=>'datetime','concluida'=>'boolean','concluida_em'=>'datetime'];
    }
    public function oportunidade() { return $this->belongsTo(Oportunidade::class); }
    public function user()         { return $this->belongsTo(User::class); }
}
