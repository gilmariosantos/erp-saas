<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CentroCusto extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'centros_custo';
    protected $fillable = ['codigo','nome','parent_id','is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function filhos() { return $this->hasMany(self::class, 'parent_id'); }
}
