<?php
namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipamento extends Model
{
    use HasFactory, SoftDeletes, HasAudit;
    protected $fillable = [
        'empresa_id','cliente_id','tipo','marca','modelo',
        'numero_serie','identificador','atributos','observacao','is_active',
    ];
    protected function casts(): array { return ['atributos' => 'array', 'is_active' => 'boolean']; }
    public function cliente()      { return $this->belongsTo(Pessoa::class, 'cliente_id'); }
    public function ordensServico(){ return $this->hasMany(OrdemServico::class); }
}
