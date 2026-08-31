<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'features'      => 'array',
            'price_monthly' => 'decimal:2',
            'price_annual'  => 'decimal:2',
            'is_active'     => 'boolean',
        ];
    }

    public function subscriptions()
    {
        return $this->hasMany(TenantSubscription::class, 'plan_id');
    }

    public function isEnterprise(): bool
    {
        return $this->max_nfe_mes >= 9999;
    }
}
