<?php

namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produto extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $fillable = [
        'codigo','codigo_barras','codigo_barras_tributavel','descricao',
        'descricao_complementar','tipo',
        'unidade_medida_id','unidade_medida_trib_id','categoria_id',
        'preco_custo','preco_venda','preco_minimo','margem_lucro',
        'controla_estoque','estoque_atual','estoque_minimo',
        'estoque_maximo','estoque_reservado','localizacao',
        'ncm','cest','cfop','origem',
        'cst_icms','csosn','aliquota_icms','reducao_bc_icms',
        'cst_ipi','codigo_enquadramento_ipi','aliquota_ipi',
        'cst_pis','aliquota_pis',
        'cst_cofins','aliquota_cofins',
        'codigo_servico','aliquota_iss',
        'retencao_iss','retencao_ir','retencao_csll',
        'retencao_inss','retencao_pis_cofins',
        'peso_bruto','peso_liquido','comprimento','largura','altura',
        'marca','modelo','fabricante',
        'foto','fotos','atributos','is_active',
    ];

    protected function casts(): array
    {
        return [
            'controla_estoque'     => 'boolean',
            'retencao_iss'         => 'boolean',
            'retencao_ir'          => 'boolean',
            'retencao_csll'        => 'boolean',
            'retencao_inss'        => 'boolean',
            'retencao_pis_cofins'  => 'boolean',
            'is_active'            => 'boolean',
            'fotos'                => 'array',
            'atributos'            => 'array',
            'preco_custo'          => 'decimal:4',
            'preco_venda'          => 'decimal:4',
            'estoque_atual'        => 'decimal:4',
            'estoque_minimo'       => 'decimal:4',
            'estoque_maximo'       => 'decimal:4',
            'estoque_reservado'    => 'decimal:4',
        ];
    }

    // ─── Relacionamentos ──────────────────────────────────────────────────────

    public function unidadeMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadeMedida::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaProduto::class, 'categoria_id');
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(MovimentacaoEstoque::class);
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isProduto(): bool  { return $this->tipo === 'P'; }
    public function isServico(): bool  { return $this->tipo === 'S'; }
    public function isCombo(): bool    { return $this->tipo === 'C'; }

    public function estoqueBaixo(): bool
    {
        return $this->controla_estoque
            && $this->estoque_atual <= $this->estoque_minimo;
    }

    public function estoqueDisponivel(): float
    {
        return max(0, $this->estoque_atual - $this->estoque_reservado);
    }

    public function valorEstoque(): float
    {
        return round($this->estoque_atual * $this->preco_custo, 2);
    }

    public function scopeAtivos($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeProdutos($query)
    {
        return $query->where('tipo', 'P');
    }

    public function scopeServicos($query)
    {
        return $query->where('tipo', 'S');
    }

    public function scopeEstoqueBaixo($query)
    {
        return $query->where('controla_estoque', true)
                     ->whereColumn('estoque_atual', '<=', 'estoque_minimo');
    }
}
