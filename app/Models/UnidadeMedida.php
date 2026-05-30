<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnidadeMedida extends Model
{
    use HasFactory;

    protected $fillable = ['sigla', 'descricao', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
