<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUsageLog extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'recorded_on',
        'users_count',
        'orders_month_count',
        'storage_bytes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'recorded_on' => 'date',
            'users_count' => 'integer',
            'orders_month_count' => 'integer',
            'storage_bytes' => 'integer',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
