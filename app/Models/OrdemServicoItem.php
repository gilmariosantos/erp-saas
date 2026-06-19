<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdemServicoItem extends Model
{
    use HasFactory;
    protected $table = 'ordem_servico_itens';
    protected $fillable = [
        'ordem_servico_id','tipo','produto_id','descricao',
        'quantidade','valor_unitario','desconto_valor','total','tecnico_id','aprovado',
    ];
    protected function casts(): array
    {
        return [
            'quantidade'     => 'decimal:4',
            'valor_unitario' => 'decimal:4',
            'total'          => 'decimal:2',
            'aprovado'       => 'boolean',
        ];
    }
    public function ordemServico() { return $this->belongsTo(OrdemServico::class); }
    public function produto()      { return $this->belongsTo(Produto::class); }
    public function isPeca(): bool { return $this->tipo === 'peca'; }
}
