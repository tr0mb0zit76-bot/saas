<?php

namespace Tests\Feature\Saas;

use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Finance\FinanceOverviewService;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\SaasTestCase;

class FinanceOverviewTenantRestrictTest extends SaasTestCase
{
    public function test_cash_flow_journal_does_not_leak_other_tenant_schedules(): void
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasColumn('orders', 'tenant_id')) {
            $this->markTestSkipped('payment_schedules / orders.tenant_id required');
        }

        TenantContext::bypass(true);

        $tenantA = Tenant::query()->create([
            'slug' => 'fin-a-'.uniqid(),
            'name' => 'Fin A',
            'status' => 'active',
            'plan' => 'enterprise',
            'settings' => ['onboarding' => ['completed_at' => now()->toIso8601String()]],
        ]);
        $tenantB = Tenant::query()->create([
            'slug' => 'fin-b-'.uniqid(),
            'name' => 'Fin B',
            'status' => 'active',
            'plan' => 'enterprise',
            'settings' => ['onboarding' => ['completed_at' => now()->toIso8601String()]],
        ]);

        $roleA = Role::query()->create([
            'tenant_id' => $tenantA->id,
            'name' => 'admin',
            'display_name' => 'Admin',
            'permissions' => RoleAccess::permissionKeys(),
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('admin'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('admin'),
        ]);
        $adminA = User::query()->create([
            'tenant_id' => $tenantA->id,
            'role_id' => $roleA->id,
            'name' => 'Admin A',
            'email' => 'fina-'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $orderA = Order::query()->create([
            'tenant_id' => $tenantA->id,
            'order_number' => 'A-'.uniqid(),
            'company_code' => 'AA',
            'manager_id' => $adminA->id,
            'status' => 'payment',
            'is_active' => true,
            'order_date' => now()->toDateString(),
        ]);
        $orderB = Order::query()->create([
            'tenant_id' => $tenantB->id,
            'order_number' => 'B-'.uniqid(),
            'company_code' => 'BB',
            'status' => 'payment',
            'is_active' => true,
            'order_date' => now()->toDateString(),
        ]);

        PaymentSchedule::query()->create([
            'tenant_id' => $tenantA->id,
            'order_id' => $orderA->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 100,
            'paid_amount' => 0,
            'remaining_amount' => 100,
            'planned_date' => now()->toDateString(),
            'status' => 'pending',
        ]);
        PaymentSchedule::query()->create([
            'tenant_id' => $tenantB->id,
            'order_id' => $orderB->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 9999,
            'paid_amount' => 0,
            'remaining_amount' => 9999,
            'planned_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        TenantContext::bypass(false);
        TenantContext::set($tenantA);

        $rows = app(FinanceOverviewService::class)->cashFlowJournal($adminA);
        $orderIds = $rows->pluck('order_id')->map(fn ($id): int => (int) $id)->all();

        $this->assertContains($orderA->id, $orderIds);
        $this->assertNotContains($orderB->id, $orderIds);
        $this->assertFalse($rows->contains(fn (array $row): bool => (float) ($row['amount'] ?? 0) === 9999.0));
    }
}
