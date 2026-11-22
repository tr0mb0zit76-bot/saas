<?php

namespace Tests\Feature\Saas;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\SaasTestCase;

/**
 * ADR-025: /finance/working-capital AR/AP register (Enterprise + scoped schedules).
 */
class WorkingCapitalRegisterTest extends SaasTestCase
{
    public function test_start_and_pro_cannot_open_working_capital(): void
    {
        foreach (['start', 'pro'] as $plan) {
            $fixture = $this->seedStaffTenant($plan);
            $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

            $this->get(route('finance.working-capital'))->assertForbidden();
        }
    }

    public function test_enterprise_register_groups_ar_ap_by_contractor(): void
    {
        $fixture = $this->seedStaffTenant('enterprise');
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

        $customer = Contractor::query()->create([
            'name' => 'Заказчик АР',
            'type' => 'customer',
            'is_active' => true,
        ]);
        $carrier = Contractor::query()->create([
            'name' => 'Перевозчик КЗ',
            'type' => 'carrier',
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'order_number' => 'WC-'.uniqid(),
            'company_code' => 'WC',
            'manager_id' => $fixture['staff']->id,
            'customer_id' => $customer->id,
            'carrier_id' => $carrier->id,
            'status' => 'payment',
            'is_active' => true,
            'order_date' => now()->toDateString(),
        ]);

        PaymentSchedule::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 80000,
            'paid_amount' => 20000,
            'remaining_amount' => 60000,
            'planned_date' => now()->toDateString(),
            'status' => 'pending',
            'counterparty_id' => $customer->id,
        ]);

        PaymentSchedule::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'order_id' => $order->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 50000,
            'paid_amount' => 0,
            'remaining_amount' => 50000,
            'planned_date' => now()->toDateString(),
            'status' => 'pending',
            'counterparty_id' => $carrier->id,
        ]);

        $this->get(route('finance.working-capital'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Finance/WorkingCapital/Register')
                ->where('register.report_kind', 'working_capital_register')
                ->where('register.totals.receivables', fn ($v): bool => (float) $v === 60000.0)
                ->where('register.totals.payables', fn ($v): bool => (float) $v === 50000.0)
                ->where('register.receivables.0.contractor_name', 'Заказчик АР')
                ->where('register.payables.0.contractor_name', 'Перевозчик КЗ')
                ->etc());
    }

    public function test_register_does_not_leak_across_tenants(): void
    {
        $tenantA = $this->seedStaffTenant('enterprise', 'wca');
        $tenantB = $this->seedStaffTenant('enterprise', 'wcb');

        $this->actingAsTenant($tenantA['tenant'], $tenantA['staff']);
        $this->seedOpenPair($tenantA['tenant'], $tenantA['staff'], 'Secret AR Co', 777777, 'Secret AP Co', 111111);

        $this->actingAsTenant($tenantB['tenant'], $tenantB['staff']);
        $this->seedOpenPair($tenantB['tenant'], $tenantB['staff'], 'Visible AR Co', 12000, 'Visible AP Co', 5000);

        $this->get(route('finance.working-capital'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('register.totals.receivables', fn ($v): bool => (float) $v === 12000.0)
                ->where('register.totals.payables', fn ($v): bool => (float) $v === 5000.0)
                ->where('register.receivables', function ($rows): bool {
                    $names = collect($rows)->pluck('contractor_name')->all();

                    return in_array('Visible AR Co', $names, true)
                        && ! in_array('Secret AR Co', $names, true);
                })
                ->etc());
    }

    public function test_manager_scope_limits_register_to_own_orders(): void
    {
        $fixture = $this->seedStaffTenant('enterprise');
        TenantContext::set($fixture['tenant']);

        $managerRole = Role::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'name' => 'manager',
            'display_name' => 'Manager',
            'permissions' => RoleAccess::permissionKeys(),
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('manager'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('manager'),
        ]);

        $manager = User::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'role_id' => $managerRole->id,
            'name' => 'WC Manager',
            'email' => 'wc-mgr-'.$fixture['tenant']->slug.'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
            'can_management_accounting' => true,
        ]);

        $other = User::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'role_id' => $managerRole->id,
            'name' => 'Other',
            'email' => 'wc-other-'.$fixture['tenant']->slug.'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
            'can_management_accounting' => true,
        ]);

        $this->seedOpenPair($fixture['tenant'], $manager, 'Mine AR', 9000, 'Mine AP', 3000);
        $this->seedOpenPair($fixture['tenant'], $other, 'Theirs AR', 880000, 'Theirs AP', 440000);

        $this->actingAsTenant($fixture['tenant'], $manager);

        $this->get(route('finance.working-capital'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('register.totals.receivables', fn ($v): bool => (float) $v === 9000.0)
                ->where('register.totals.payables', fn ($v): bool => (float) $v === 3000.0)
                ->etc());
    }

    /**
     * @return array{tenant: Tenant, staff: User}
     */
    private function seedStaffTenant(string $plan, string $suffix = ''): array
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'wc-'.$plan.($suffix !== '' ? '-'.$suffix : '').'-'.uniqid(),
            'name' => 'WC '.$plan,
            'status' => 'active',
            'plan' => $plan,
            'settings' => ['onboarding' => ['completed_at' => now()->toIso8601String()]],
        ]);

        $adminRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'admin',
            'display_name' => 'Admin',
            'permissions' => RoleAccess::permissionKeys(),
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('admin'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('admin'),
        ]);

        TenantContext::bypass(false);
        TenantContext::set($tenant);

        $staff = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $adminRole->id,
            'name' => 'WC Staff',
            'email' => 'wc-'.$tenant->slug.'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
            'can_management_accounting' => $plan === 'enterprise',
        ]);

        return ['tenant' => $tenant, 'staff' => $staff];
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        config(['saas.default_tenant_slug' => $tenant->slug]);
        TenantContext::set($tenant);
        $this->actingAs($user);

        return $this;
    }

    private function seedOpenPair(
        Tenant $tenant,
        User $manager,
        string $customerName,
        float $ar,
        string $carrierName,
        float $ap,
    ): void {
        TenantContext::set($tenant);

        $customer = Contractor::query()->create([
            'name' => $customerName,
            'type' => 'customer',
            'is_active' => true,
        ]);
        $carrier = Contractor::query()->create([
            'name' => $carrierName,
            'type' => 'carrier',
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'tenant_id' => $tenant->id,
            'order_number' => 'WCP-'.uniqid(),
            'company_code' => 'WC',
            'manager_id' => $manager->id,
            'customer_id' => $customer->id,
            'carrier_id' => $carrier->id,
            'status' => 'payment',
            'is_active' => true,
            'order_date' => now()->toDateString(),
        ]);

        PaymentSchedule::query()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => $ar,
            'paid_amount' => 0,
            'remaining_amount' => $ar,
            'planned_date' => now()->toDateString(),
            'status' => 'pending',
            'counterparty_id' => $customer->id,
        ]);

        PaymentSchedule::query()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => $ap,
            'paid_amount' => 0,
            'remaining_amount' => $ap,
            'planned_date' => now()->toDateString(),
            'status' => 'pending',
            'counterparty_id' => $carrier->id,
        ]);
    }
}
