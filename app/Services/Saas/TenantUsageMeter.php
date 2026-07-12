<?php

namespace App\Services\Saas;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Schema;

final class TenantUsageMeter
{
    public function recordFor(Tenant $tenant, ?\Carbon\Carbon $asOf = null): void
    {
        $asOf ??= now();
        $recordedOn = $asOf->toDateString();

        TenantContext::runAs($tenant, function () use ($tenant, $recordedOn): void {
            $usersCount = User::query()->where('tenant_id', $tenant->id)->count();
            $ordersMonth = Order::query()
                ->where('tenant_id', $tenant->id)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();
            $storageBytes = $this->measureStorageBytes($tenant);

            $tenant->usageLogs()->updateOrCreate(
                ['recorded_on' => $recordedOn],
                [
                    'users_count' => $usersCount,
                    'orders_month_count' => $ordersMonth,
                    'storage_bytes' => $storageBytes,
                ],
            );
        });
    }

    public function measureStorageBytes(Tenant $tenant): int
    {
        return TenantContext::runAs($tenant, function () use ($tenant): int {
            $disk = \App\Support\TenantStorage::disk();
            $prefix = 'tenants/'.$tenant->id;

            if (! $disk->exists($prefix)) {
                return 0;
            }

            $total = 0;

            foreach ($disk->allFiles($prefix) as $path) {
                $total += (int) $disk->size($path);
            }

            return $total;
        });
    }

    public function currentStorageBytes(?Tenant $tenant = null): int
    {
        $tenant ??= TenantContext::get();

        if ($tenant === null) {
            return 0;
        }

        $latest = $tenant->usageLogs()->orderByDesc('recorded_on')->value('storage_bytes');

        if ($latest !== null) {
            return (int) $latest;
        }

        return $this->measureStorageBytes($tenant);
    }
}
