<?php
namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrdemServico extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $table = 'ordens_servico';

    protected $fillable = [
        'empresa_id','numero','tipo_os_id','cliente_id','equipamento_id',
        'status_id','tecnico_id','vendedor_id',
        'data_abertura','data_previsao','data_conclusao','data_aprovacao_cliente',
        'descricao_problema','diagnostico','solucao','observacoes_internas',
        'total_servicos','total_pecas','total_desconto','total_geral',
        'garantia_dias','garantia_ate',
        'nfse_id','lancamento_id','estoque_baixado','situacao','created_by',
    ];

    protected function casts(): array
    {
        return [
            'data_abertura'          => 'date',
            'data_previsao'          => 'date',
            'data_conclusao'         => 'date',
            'data_aprovacao_cliente' => 'datetime',
            'garantia_ate'           => 'date',
            'total_geral'            => 'decimal:2',
            'total_servicos'         => 'decimal:2',
            'total_pecas'            => 'decimal:2',
            'estoque_baixado'        => 'boolean',
        ];
    }

    public function empresa()     { return $this->belongsTo(Empresa::class); }
    public function cliente()     { return $this->belongsTo(Pessoa::class, 'cliente_id'); }
    public function equipamento() { return $this->belongsTo(Equipamento::class); }
    public function tipoOs()      { return $this->belongsTo(TipoOs::class, 'tipo_os_id'); }
    public function status()      { return $this->belongsTo(StatusOs::class, 'status_id'); }
    public function tecnico()     { return $this->belongsTo(User::class, 'tecnico_id'); }
    public function itens()       { return $this->hasMany(OrdemServicoItem::class); }
    public function historico()   { return $this->hasMany(OrdemServicoHistorico::class)->latest(); }
    public function nfse()        { return $this->belongsTo(Nfse::class); }
    public function lancamento()  { return $this->belongsTo(Lancamento::class); }

    public function servicos()    { return $this->itens()->where('tipo', 'servico'); }
    public function pecas()       { return $this->itens()->where('tipo', 'peca'); }

    public function isConcluida(): bool { return in_array($this->situacao, ['concluida', 'entregue']); }
    public function isCancelada(): bool { return $this->situacao === 'cancelada'; }
    public function podeFinalizar(): bool { return in_array($this->situacao, ['aprovada', 'em_execucao']); }

    public function recalcularTotais(): void
    {
        $this->total_servicos = $this->servicos()->sum('total');
        $this->total_pecas    = $this->pecas()->sum('total');
        $this->total_desconto = $this->itens()->sum('desconto_valor');
        $this->total_geral    = $this->total_servicos + $this->total_pecas;
        $this->save();
    }
}
