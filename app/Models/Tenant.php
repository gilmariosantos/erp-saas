<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $guarded = [];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'email_responsavel',
            'razao_social',
            'cnpj',
            'status',
            'suspenso_em',
            'motivo_suspensao',
        ];
    }

    protected function casts(): array
    {
        return ['suspenso_em' => 'datetime'];
    }

    public function isAtivo(): bool { return $this->status === 'ativo'; }
    public function isSuspenso(): bool { return $this->status === 'suspenso'; }
}
