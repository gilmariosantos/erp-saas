<?php

namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'razao_social','nome_fantasia','cnpj','cpf','ie','im','suframa',
        'tipo_pessoa','regime_tributario',
        'logradouro','numero','complemento','bairro','municipio',
        'codigo_municipio','uf','cep','pais','codigo_pais',
        'telefone','email','website',
        'csc_id','csc_token',
        'serie_nfe','serie_nfce','numero_nfe','numero_nfce','ambiente_nfe','versao_nfe',
        'serie_cte','numero_cte','ambiente_cte','rntrc',
        'serie_mdfe','numero_mdfe','ambiente_mdfe',
        'certificado_path','certificado_senha','certificado_validade',
        'cnae_principal','codigo_tributacao_municipio','aliquota_iss',
        'senha_prefeitura','usuario_prefeitura',
        'is_active','is_matriz','empresa_matriz_id','logo','config',
    ];

    protected function casts(): array
    {
        return [
            'is_active'            => 'boolean',
            'is_matriz'            => 'boolean',
            'certificado_validade' => 'date',
            'config'               => 'array',
            'certificado_senha'    => 'encrypted',
            'senha_prefeitura'     => 'encrypted',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'empresa_users')
                    ->withPivot('is_default')->withTimestamps();
    }

    public function nfes(): HasMany      { return $this->hasMany(Nfe::class); }
    public function ctes(): HasMany      { return $this->hasMany(Cte::class); }
    public function lancamentos(): HasMany { return $this->hasMany(Lancamento::class); }
    public function contasBancarias(): HasMany { return $this->hasMany(ContaBancaria::class); }
    public function pedidosCompra(): HasMany { return $this->hasMany(PedidoCompra::class); }

    public function cnpjSemMascara(): string
    {
        return preg_replace('/\D/', '', $this->cnpj ?? '');
    }

    public function certificadoVencido(): bool
    {
        return $this->certificado_validade && $this->certificado_validade->isPast();
    }
}
