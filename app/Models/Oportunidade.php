<?php
namespace App\Models;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Oportunidade extends Model
{
    use HasFactory, SoftDeletes, HasAudit;
    protected $fillable = [
        'empresa_id','funil_id','etapa_id','pessoa_id','responsavel_id',
        'titulo','valor','previsao_fechamento','fechada_em',
        'resultado','motivo_perda','observacao','tags','created_by',
    ];
    protected function casts(): array
    {
        return [
            'valor'               => 'decimal:2',
            'previsao_fechamento' => 'date',
            'fechada_em'          => 'date',
            'tags'                => 'array',
        ];
    }
    public function funil()       { return $this->belongsTo(FunilVenda::class); }
    public function etapa()       { return $this->belongsTo(EtapaFunil::class); }
    public function pessoa()      { return $this->belongsTo(Pessoa::class); }
    public function responsavel() { return $this->belongsTo(User::class, 'responsavel_id'); }
    public function atividades()  { return $this->hasMany(AtividadeCrm::class); }
    public function pedidos()     { return $this->hasMany(PedidoVenda::class); }

    public function isGanha(): bool  { return $this->resultado === 'ganha'; }
    public function isPerdida(): bool{ return $this->resultado === 'perdida'; }
}
