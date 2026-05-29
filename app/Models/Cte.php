<?php

namespace App\Models;

use App\Enums\CTeStatus;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cte extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $table = 'ctes';

    protected $fillable = [
        'empresa_id','chave_acesso','numero','serie','modelo','ambiente',
        'tipo_emissao','tipo_ct','tipo_servico','data_emissao',
        'natureza_operacao','cfop','modal',
        'emitente_cnpj','emitente_razao_social','emitente_ie','emitente_uf','emitente_rntrc',
        'remetente_id','remetente_cnpj_cpf','remetente_nome','remetente_ie','remetente_endereco',
        'destinatario_id','destinatario_cnpj_cpf','destinatario_nome','destinatario_ie','destinatario_endereco',
        'tomador','tomador_id','expedidor_id','recebedor_id',
        'municipio_inicio','uf_inicio','municipio_fim','uf_fim',
        'codigo_municipio_inicio','codigo_municipio_fim',
        'valor_total_servico','valor_carga','valor_receber','valor_desconto',
        'base_calc_icms','aliquota_icms','valor_icms','percentual_reducao_bc',
        'cst_icms','csosn',
        'produto_predominante','outras_caracteristicas','valor_total_mercadoria',
        'carga_unidade_medida','carga_tipo_medida',
        'rntrc','occ_numero','occ_emitente','occ_data_emissao',
        'ciot','ciot_cpf_cnpj','ciot_emitido_em','ciot_protocolo',
        'ciot_valor_frete','ciot_pedagio',
        'veiculo_placa','veiculo_uf','veiculo_rntrc','reboques',
        'motorista_cpf','motorista_nome',
        'informacoes_complementares','informacoes_fisco','nfes_referenciadas',
        'xml_enviado','xml_retorno','xml_cancelamento',
        'protocolo_autorizacao','data_autorizacao',
        'status','motivo_rejeicao','codigo_retorno','tentativas_envio',
        'path_xml','path_pdf','created_by',
    ];

    protected function casts(): array
    {
        return [
            'status'             => CTeStatus::class,
            'data_emissao'       => 'datetime',
            'data_autorizacao'   => 'datetime',
            'ciot_emitido_em'    => 'datetime',
            'occ_data_emissao'   => 'date',
            'reboques'           => 'array',
            'nfes_referenciadas' => 'array',
            'remetente_endereco' => 'array',
            'destinatario_endereco' => 'array',
        ];
    }

    public function empresa()      { return $this->belongsTo(Empresa::class); }
    public function remetente()    { return $this->belongsTo(Pessoa::class, 'remetente_id'); }
    public function destinatario() { return $this->belongsTo(Pessoa::class, 'destinatario_id'); }
    public function tomadorPessoa(){ return $this->belongsTo(Pessoa::class, 'tomador_id'); }
    public function documentos()   { return $this->hasMany(CteDocumento::class); }
    public function componentes()  { return $this->hasMany(CteComponenteValor::class); }

    public function isAutorizada(): bool { return $this->status === CTeStatus::AUTORIZADA; }
    public function temCiot(): bool      { return ! empty($this->ciot); }
}
