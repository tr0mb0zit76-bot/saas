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

    private function resolveTenant(): ?Tenant
    {
        return \App\Support\TenantContext::get();
    }
}
