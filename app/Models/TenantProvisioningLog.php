<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantProvisioningLog extends Model
{
    protected $fillable = [
        'tenant_id', 'email_responsavel', 'razao_social', 'cnpj',
        'status', 'erro', 'metadata',
    ];
    protected function casts(): array { return ['metadata' => 'array']; }
}
