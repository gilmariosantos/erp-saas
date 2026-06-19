<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoOs extends Model
{
    protected $table = 'tipos_os';
    protected $fillable = ['empresa_id','nome','prefixo','exige_equipamento','is_active'];
    protected function casts(): array { return ['exige_equipamento' => 'boolean', 'is_active' => 'boolean']; }
}
