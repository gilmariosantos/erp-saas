<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormaPagamento extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'formas_pagamento';
    protected $fillable = ['nome','tipo','prazo_compensacao','taxa_percentual','taxa_fixa','gera_boleto','is_active'];
    protected function casts(): array { return ['gera_boleto' => 'boolean','is_active' => 'boolean']; }
}
