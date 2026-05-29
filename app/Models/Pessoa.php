<?php

namespace App\Models;

use App\Traits\HasAudit;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pessoa extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $fillable = [
        'nome','nome_fantasia','tipo_pessoa','cnpj','cpf','rg','ie',
        'ie_suframa','im','indicador_ie',
        'is_cliente','is_fornecedor','is_transportadora','is_vendedor','is_funcionario',
        'logradouro','numero','complemento','bairro','municipio',
        'codigo_municipio','uf','cep','pais','codigo_pais',
        'telefone','celular','email','email_nfe','website',
        'banco','agencia','conta','pix_chave','pix_tipo',
        'limite_credito','prazo_pagamento',
        'categoria','segmento','vendedor_id','tabela_preco',
        'data_nascimento','nacionalidade','observacao',
        'contatos_adicionais','enderecos_adicionais','is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_cliente'          => 'boolean',
            'is_fornecedor'       => 'boolean',
            'is_transportadora'   => 'boolean',
            'is_vendedor'         => 'boolean',
            'is_funcionario'      => 'boolean',
            'is_active'           => 'boolean',
            'data_nascimento'     => 'date',
            'contatos_adicionais' => 'array',
            'enderecos_adicionais'=> 'array',
            'limite_credito'      => 'decimal:2',
        ];
    }

    public function lancamentos() { return $this->hasMany(Lancamento::class); }
    public function nfes()        { return $this->hasMany(Nfe::class, 'destinatario_id'); }
    public function ctes()        { return $this->hasMany(Cte::class, 'destinatario_id'); }
    public function pedidosCompra() { return $this->hasMany(PedidoCompra::class, 'fornecedor_id'); }

    public function documentoFormatado(): string
    {
        if ($this->tipo_pessoa === 'PJ' && $this->cnpj) return $this->cnpj;
        if ($this->tipo_pessoa === 'PF' && $this->cpf)  return $this->cpf;
        return '';
    }
}
