<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'subscription_id', 'numero', 'valor', 'valor_pago',
        'vencimento', 'competencia', 'pago_em', 'status',
        'gateway', 'gateway_invoice_id', 'gateway_payment_id', 'metodo_pagamento',
        'link_pagamento', 'pix_copia_cola', 'pix_qrcode',
        'linha_digitavel_boleto', 'url_boleto', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'vencimento'  => 'date',
            'competencia' => 'date',
            'pago_em'     => 'date',
            'valor'       => 'decimal:2',
            'valor_pago'  => 'decimal:2',
            'metadata'    => 'array',
        ];
    }

    public function tenant()       { return $this->belongsTo(Tenant::class); }
    public function subscription() { return $this->belongsTo(TenantSubscription::class, 'subscription_id'); }

    public function isPaga(): bool   { return $this->status === 'pago'; }
    public function isVencida(): bool { return $this->status === 'vencido' || ($this->status === 'pendente' && $this->vencimento->isPast()); }
}
