<?php

// ─── PedidoVenda ──────────────────────────────────────────────────────────────

namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedidoVenda extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $fillable = [
        'empresa_id','numero','tipo','cliente_id','vendedor_id',
        'oportunidade_id','forma_pagamento_id','tabela_preco_id',
        'data_pedido','data_validade','data_entrega_prevista','data_entrega_real',
        'status',
        'total_produtos','total_desconto','total_frete','total_outras','total_pedido',
        'entrega_logradouro','entrega_numero','entrega_bairro',
        'entrega_municipio','entrega_uf','entrega_cep',
        'observacao','observacao_interna','condicao_pagamento',
        'nfe_id','created_by','updated_by',
    ];

    protected function casts(): array
    {
        return [
            'data_pedido'           => 'date',
            'data_validade'         => 'date',
            'data_entrega_prevista' => 'date',
            'data_entrega_real'     => 'date',
            'total_pedido'          => 'decimal:2',
            'total_desconto'        => 'decimal:2',
        ];
    }

    public function empresa()       { return $this->belongsTo(Empresa::class); }
    public function cliente()       { return $this->belongsTo(Pessoa::class, 'cliente_id'); }
    public function vendedor()      { return $this->belongsTo(User::class, 'vendedor_id'); }
    public function oportunidade()  { return $this->belongsTo(Oportunidade::class); }
    public function nfe()           { return $this->belongsTo(Nfe::class); }
    public function itens()         { return $this->hasMany(PedidoVendaItem::class); }
    public function comissoes()     { return $this->hasMany(Comissao::class); }

    public function isOrcamento(): bool { return $this->tipo === 'orcamento'; }
    public function isPedido(): bool    { return $this->tipo === 'pedido'; }
    public function isFaturado(): bool  { return $this->status === 'faturado'; }

    public function margemTotal(): float
    {
        if ($this->total_pedido <= 0) return 0;
        $custoTotal = $this->itens->sum(fn ($i) => $i->custo_unitario * $i->quantidade);
        return round((($this->total_pedido - $custoTotal) / $this->total_pedido) * 100, 2);
    }

    public function recalcularTotais(): void
    {
        $this->total_produtos = $this->itens->sum('total');
        $this->total_desconto = $this->itens->sum('desconto_valor');
        $this->total_pedido   = $this->total_produtos - $this->total_desconto
                              + $this->total_frete + $this->total_outras;
        $this->save();
    }
}
