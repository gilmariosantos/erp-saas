<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adiciona rastreamento de quem criou/atualizou o registro.
 * Requer colunas created_by e updated_by na tabela.
 */
trait HasAudit
{
    public static function bootHasAudit(): void
    {
        static::creating(function ($model) {
            if (auth()->check() && $model->isFillable('created_by')) {
                $model->created_by ??= auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check() && $model->isFillable('updated_by')) {
                $model->updated_by = auth()->id();
            }
        });
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function atualizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
