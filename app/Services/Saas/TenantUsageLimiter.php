<?php

namespace App\Services\Saas;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class TenantUsageLimiter
{
    public function assertCanAddUser(?Tenant $tenant = null): void
    {
        $tenant ??= $this->resolveTenant();

        if ($tenant === null) {
            return;
        }

        $limit = $tenant->planLimits()['users'] ?? null;

        if ($limit === null) {
            return;
        }

        $count = User::query()->where('tenant_id', $tenant->id)->count();

        if ($count >= $limit) {
            throw ValidationException::withMessages([
                'email' => "Достигнут лимит тарифа: не более {$limit} пользователей. Обратитесь к администратору платформы для апгрейда.",
            ]);
        }
    }

    public function assertCanCreateOrder(?Tenant $tenant = null): void
    {
        $tenant ??= $this->resolveTenant();

        if ($tenant === null) {
            return;
        }

        $limit = $tenant->planLimits()['orders_per_month'] ?? null;

        if ($limit === null) {
            return;
        }

        $count = Order::query()
            ->where('tenant_id', $tenant->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        if ($count >= $limit) {
            throw ValidationException::withMessages([
                'order_number' => "Достигнут лимит тарифа: не более {$limit} заказов в месяц. Обратитесь к администратору платформы для апгрейда.",
            ]);
        }
    }

    public function assertCanStoreBytes(int $additionalBytes, ?Tenant $tenant = null): void
    {
        $tenant ??= $this->resolveTenant();

        if ($tenant === null || $additionalBytes <= 0) {
            return;
        }

        $limitMb = $tenant->planLimits()['storage_mb'] ?? null;

        if ($limitMb === null) {
            return;
        }

        $limitBytes = (int) $limitMb * 1024 * 1024;
        $currentBytes = app(TenantUsageMeter::class)->measureStorageBytes($tenant);

        if ($currentBytes + $additionalBytes > $limitBytes) {
            throw ValidationException::withMessages([
                'file' => 'Достигнут лимит хранилища тарифа ('.$limitMb.' МБ). Обратитесь к администратору платформы.',
            ]);
        }
    }

    private function resolveTenant(): ?Tenant
    {
        return \App\Support\TenantContext::get();
    }
}
