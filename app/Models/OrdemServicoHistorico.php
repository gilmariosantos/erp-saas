<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdemServicoHistorico extends Model
{
    protected $table = 'ordem_servico_historico';
    protected $fillable = ['ordem_servico_id','user_id','status_id','acao','descricao'];
    public function user()   { return $this->belongsTo(User::class); }
    public function status() { return $this->belongsTo(StatusOs::class, 'status_id'); }
}
