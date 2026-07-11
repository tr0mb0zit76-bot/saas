<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantSubscription extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'status',
        'plan',
        'trial_ends_at',
        'billing_period_start',
        'billing_period_end',
        'suspended_at',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'suspended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<TenantInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(TenantInvoice::class, 'tenant_id', 'tenant_id');
    }

    public function isAccessible(): bool
    {
        return in_array($this->status, ['trial', 'active', 'past_due'], true);
    }
}
