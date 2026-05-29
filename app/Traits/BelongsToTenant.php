<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Garante que queries sempre filtrem pelo empresa_id da sessão.
 * Protege contra vazamento de dados entre empresas do mesmo tenant.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::creating(function ($model) {
            if (empty($model->empresa_id) && auth()->check()) {
                $model->empresa_id = auth()->user()->empresaAtualId();
            }
        });

        static::addGlobalScope('empresa', function (Builder $builder) {
            if (auth()->check() && $empresaId = auth()->user()->empresaAtualId()) {
                $builder->where(
                    $builder->getModel()->getTable() . '.empresa_id',
                    $empresaId
                );
            }
        });
    }

    public function scopeSemFiltroEmpresa(Builder $query): Builder
    {
        return $query->withoutGlobalScope('empresa');
    }
}
