<?php

namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'avatar',
        'is_active', 'is_admin', 'locale', 'timezone',
        'preferences', 'last_login_at', 'last_login_ip',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'        => 'datetime',
            'last_login_at'            => 'datetime',
            'two_factor_confirmed_at'  => 'datetime',
            'password'                 => 'hashed',
            'is_active'                => 'boolean',
            'is_admin'                 => 'boolean',
            'preferences'              => 'array',
        ];
    }

    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'empresa_users')
                    ->withPivot('is_default')
                    ->withTimestamps();
    }

    public function empresaAtiva(): ?Empresa
    {
        return $this->empresas()->wherePivot('is_default', true)->first()
            ?? $this->empresas()->first();
    }

    public function empresaAtualId(): ?int
    {
        return $this->empresaAtiva()?->id;
    }

    public function temDoisFatores(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }
}
