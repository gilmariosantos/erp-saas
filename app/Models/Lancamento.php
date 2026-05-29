<?php

namespace App\Models;

use App\Enums\LancamentoStatus;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lancamento extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $fillable = [
        'empresa_id','tipo','descricao','numero_documento',
        'codigo_barras','linha_digitavel',
        'data_emissao','data_vencimento','data_competencia','data_pagamento',
        'valor_original','valor_juros','valor_multa','valor_desconto','valor_pago',
        'pessoa_id','conta_bancaria_id','plano_conta_id',
        'centro_custo_id','forma_pagamento_id',
        'origem_tipo','origem_id',
        'grupo_parcelas','parcela_numero','parcela_total',
        'status','observacao','tags',
        'is_recorrente','recorrencia_tipo','recorrencia_qtd',
        'created_by','updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status'          => LancamentoStatus::class,
            'data_emissao'    => 'date',
            'data_vencimento' => 'date',
            'data_competencia'=> 'date',
            'data_pagamento'  => 'date',
            'valor_original'  => 'decimal:2',
            'valor_juros'     => 'decimal:2',
            'valor_multa'     => 'decimal:2',
            'valor_desconto'  => 'decimal:2',
            'valor_pago'      => 'decimal:2',
            'is_recorrente'   => 'boolean',
            'tags'            => 'array',
        ];
    }

    // ─── Relacionamentos ──────────────────────────────────────────────────────

    public function empresa(): BelongsTo       { return $this->belongsTo(Empresa::class); }
    public function pessoa(): BelongsTo        { return $this->belongsTo(Pessoa::class); }
    public function contaBancaria(): BelongsTo { return $this->belongsTo(ContaBancaria::class); }
    public function planoConta(): BelongsTo    { return $this->belongsTo(PlanoConta::class, 'plano_conta_id'); }
    public function centroCusto(): BelongsTo   { return $this->belongsTo(CentroCusto::class); }
    public function formaPagamento(): BelongsTo{ return $this->belongsTo(FormaPagamento::class); }
    public function baixas(): HasMany          { return $this->hasMany(LancamentoBaixa::class); }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePagar($query)   { return $query->where('tipo', 'pagar'); }
    public function scopeReceber($query) { return $query->where('tipo', 'receber'); }
    public function scopeAbertos($query) { return $query->whereIn('status', ['aberto', 'parcial', 'vencido']); }
    public function scopeVencidos($query){
        return $query->where('data_vencimento', '<', today())
                     ->whereIn('status', ['aberto', 'parcial']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function saldoAberto(): float
    {
        return max(0, round(
            $this->valor_original + $this->valor_juros
            + $this->valor_multa - $this->valor_desconto - $this->valor_pago,
            2
        ));
    }

    public function isVencido(): bool
    {
        return $this->data_vencimento->isPast()
            && in_array($this->status, [LancamentoStatus::ABERTO, LancamentoStatus::PARCIAL]);
    }

    public function diasAtraso(): int
    {
        if (! $this->isVencido()) return 0;
        return $this->data_vencimento->diffInDays(today());
    }
}
