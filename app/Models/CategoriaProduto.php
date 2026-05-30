<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoriaProduto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['nome', 'parent_id', 'nivel', 'cor', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function filhos(): HasMany   { return $this->hasMany(self::class, 'parent_id'); }
    public function produtos(): HasMany { return $this->hasMany(Produto::class, 'categoria_id'); }
}
