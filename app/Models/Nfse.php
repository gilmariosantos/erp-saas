<?php
namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nfse extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $table = 'nfses';

    protected $fillable = [
        'empresa_id','tomador_id','numero','numero_rps','serie_rps','tipo_rps',
        'ambiente','padrao_municipal','codigo_municipio','codigo_municipio_prestacao',
        'data_emissao','data_competencia','descricao_servico','codigo_servico',
        'codigo_tributacao_municipio','cnae','natureza_operacao',
        'valor_servico','valor_deducoes','valor_pis','valor_cofins',
        'valor_inss','valor_ir','valor_csll','outras_retencoes',
        'valor_iss','aliquota_iss','base_calculo','valor_liquido','iss_retido',
        'tomador_cnpj_cpf','tomador_nome','tomador_email','tomador_ie','tomador_im','tomador_endereco',
        'numero_verificacao','codigo_verificacao',
        'xml_enviado','xml_retorno','xml_cancelamento','path_xml','path_pdf','link_nfse',
        'status','motivo_rejeicao','created_by',
    ];

    protected function casts(): array
    {
        return [
            'data_emissao'      => 'datetime',
            'data_competencia'  => 'datetime',
            'valor_servico'     => 'decimal:2',
            'valor_liquido'     => 'decimal:2',
            'aliquota_iss'      => 'decimal:4',
            'iss_retido'        => 'boolean',
            'tomador_endereco'  => 'array',
        ];
    }

    public function empresa() { return $this->belongsTo(Empresa::class); }
    public function tomador() { return $this->belongsTo(Pessoa::class, 'tomador_id'); }
    public function isAutorizada(): bool { return $this->status === 'autorizada'; }
}
