<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusOs extends Model
{
    protected $table = 'status_os';
    protected $fillable = ['empresa_id','nome','cor','ordem','is_inicial','is_final','is_cancelado'];
    protected function casts(): array
    {
        return ['is_inicial' => 'boolean', 'is_final' => 'boolean', 'is_cancelado' => 'boolean'];
    }
}
