<?php

namespace App\Models;

use App\Enums\NFeStatus;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nfe extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $table = 'nfes';

    protected $fillable = [
        'empresa_id','chave_acesso','numero','serie','modelo',
        'tipo_emissao','finalidade','operacao','ambiente',
        'data_emissao','data_saida_entrada',
        'emitente_cnpj','emitente_ie','emitente_razao_social','emitente_uf',
        'destinatario_id','destinatario_cnpj_cpf','destinatario_nome',
        'destinatario_email','destinatario_ie','destinatario_uf',
        'destinatario_indicador_ie','destinatario_endereco',
        'total_produtos','total_desconto','total_frete','total_seguro',
        'total_outras','total_ipi','total_icms','total_icms_st',
        'total_pis','total_cofins','total_nota',
        'modalidade_frete','transportadora_id',
        'transporte_veiculo','transporte_peso_bruto','transporte_peso_liquido',
        'transporte_quantidade_volumes','transporte_especie',
        'transporte_marca','transporte_numeracao',
        'informacoes_complementares','informacoes_fisco',
        'natureza_operacao','cfop_predominante','nfes_referenciadas',
        'xml_enviado','xml_retorno','xml_cancelamento','xml_carta_correcao',
        'protocolo_autorizacao','data_autorizacao','digest_value',
        'status','motivo_rejeicao','codigo_retorno','descricao_retorno',
        'tentativas_envio','ultima_tentativa_em',
        'cancelada_em','motivo_cancelamento','protocolo_cancelamento',
        'cce_em','cce_descricao','protocolo_cce','cce_sequencia',
        'path_xml','path_pdf','created_by','updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status'                 => NFeStatus::class,
            'data_emissao'           => 'datetime',
            'data_saida_entrada'     => 'datetime',
            'data_autorizacao'       => 'datetime',
            'cancelada_em'           => 'datetime',
            'cce_em'                 => 'datetime',
            'ultima_tentativa_em'    => 'datetime',
            'destinatario_endereco'  => 'array',
            'transporte_veiculo'     => 'array',
            'nfes_referenciadas'     => 'array',
            'total_nota'             => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo      { return $this->belongsTo(Empresa::class); }
    public function destinatario(): BelongsTo { return $this->belongsTo(Pessoa::class, 'destinatario_id'); }
    public function transportadora(): BelongsTo { return $this->belongsTo(Pessoa::class, 'transportadora_id'); }
    public function itens(): HasMany          { return $this->hasMany(NfeItem::class); }
    public function cobrancas(): HasMany      { return $this->hasMany(NfeCobranca::class); }
    public function volumes(): HasMany        { return $this->hasMany(NfeVolume::class); }

    public function isAutorizada(): bool  { return $this->status === NFeStatus::AUTORIZADA; }
    public function isCancelada(): bool   { return $this->status === NFeStatus::CANCELADA; }
    public function podeEmitir(): bool    { return $this->status->podeEmitir(); }
    public function podeCancelar(): bool  { return $this->status->podeCancelar(); }

    public function scopeAutorizadas($query)  { return $query->where('status', NFeStatus::AUTORIZADA); }
    public function scopePendentes($query)    { return $query->where('status', NFeStatus::PENDENTE); }
}
