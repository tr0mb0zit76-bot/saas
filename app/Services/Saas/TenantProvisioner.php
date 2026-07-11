<?php

namespace App\Services\Saas;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use App\Support\TenantStorage;

final class TenantProvisioner
{
    /**
     * @var list<array{name: string, display_name: string}>
     */
    private const DEFAULT_ROLES = [
        ['name' => 'admin', 'display_name' => 'Администратор'],
        ['name' => 'supervisor', 'display_name' => 'Руководитель'],
        ['name' => 'manager', 'display_name' => 'Менеджер'],
        ['name' => 'dispatcher', 'display_name' => 'Диспетчер'],
        ['name' => 'accountant', 'display_name' => 'Бухгалтер'],
        ['name' => 'clerk', 'display_name' => 'Делопроизводитель'],
        ['name' => 'viewer', 'display_name' => 'Наблюдатель'],
    ];

    public function provision(Tenant $tenant): Tenant
    {
        TenantContext::runAs($tenant, function () use ($tenant): void {
            TenantStorage::provisionFor($tenant);
            $this->seedRoles($tenant);
        });

        $this->syncSubscription($tenant);

        return $tenant->fresh(['subscription']);
    }

    /**
     * @return array<string, Role>
     */
    public function seedRoles(Tenant $tenant): array
    {
        $roles = [];

        foreach (self::DEFAULT_ROLES as $definition) {
            $name = $definition['name'];

            $roles[$name] = Role::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name],
                [
                    'tenant_id' => $tenant->id,
                    'display_name' => $definition['display_name'],
                    'description' => 'Traklo Pro default role',
                    'permissions' => RoleAccess::permissionKeys(),
                    'visibility_areas' => RoleAccess::defaultVisibilityAreas($name),
                    'visibility_scopes' => RoleAccess::defaultVisibilityScopes($name),
                ],
            );
        }

        return $roles;
    }

    public function syncSubscription(Tenant $tenant): TenantSubscription
    {
        $existing = TenantSubscription::query()->where('tenant_id', $tenant->id)->first();

        $trialEndsAt = $tenant->trial_ends_at;

        if ($tenant->status === 'trial' && $trialEndsAt === null) {
            $trialEndsAt = now()->addDays((int) config('saas.trial_days', 14));
            $tenant->forceFill(['trial_ends_at' => $trialEndsAt])->save();
        }

        $subscriptionStatus = match ($tenant->status) {
            'active' => 'active',
            'trial' => 'trial',
            'suspended' => 'suspended',
            default => 'trial',
        };

        $billingStart = $existing?->billing_period_start;
        $billingEnd = $existing?->billing_period_end;

        if ($subscriptionStatus === 'active' && $billingStart === null) {
            $billingStart = now()->toDateString();
            $billingEnd = now()->addMonth()->toDateString();
        }

        return TenantSubscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'status' => $subscriptionStatus,
                'plan' => $tenant->planKey(),
                'trial_ends_at' => $trialEndsAt,
                'billing_period_start' => $billingStart,
                'billing_period_end' => $billingEnd,
                'suspended_at' => $tenant->status === 'suspended' ? ($existing?->suspended_at ?? now()) : null,
            ],
        );
    }
}
