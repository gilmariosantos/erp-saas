<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantSubscription extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_subscriptions';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'trial_ends_at'        => 'date',
            'current_period_start' => 'date',
            'current_period_end'   => 'date',
            'cancelled_at'         => 'date',
            'proxima_cobranca'     => 'date',
        ];
    }

    public function tenant()  { return $this->belongsTo(Tenant::class); }
    public function invoices(){ return $this->hasMany(Invoice::class, 'subscription_id'); }

    public function isTrial(): bool  { return $this->status === 'trial'; }
    public function isActive(): bool { return $this->status === 'active'; }
    public function trialExpirado(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at && $this->trial_ends_at->isPast();
    }
}
