<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NfeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'nfe_id','produto_id','numero_item',
        'codigo_produto','codigo_barras','descricao','ncm','cest','cfop',
        'unidade','quantidade','valor_unitario','valor_bruto',
        'desconto','frete','seguro','outras_despesas','valor_total',
        'origem','compoe_total',
        'cst_icms','csosn','base_calc_icms','aliquota_icms','valor_icms',
        'base_calc_icms_st','aliquota_icms_st','valor_icms_st',
        'cst_ipi','codigo_enquadramento_ipi','base_calc_ipi','aliquota_ipi','valor_ipi',
        'cst_pis','base_calc_pis','aliquota_pis','valor_pis',
        'cst_cofins','base_calc_cofins','aliquota_cofins','valor_cofins',
        'informacoes_adicionais',
    ];

    protected function casts(): array
    {
        return [
            'compoe_total'  => 'boolean',
            'quantidade'    => 'decimal:4',
            'valor_unitario'=> 'decimal:10',
            'valor_total'   => 'decimal:2',
        ];
    }

    public function nfe(): BelongsTo     { return $this->belongsTo(Nfe::class); }
    public function produto(): BelongsTo { return $this->belongsTo(Produto::class); }
}
