<?php

namespace Tests\Feature\Saas;

use App\Models\Contractor;
use App\Models\ManagementBankAccount;
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
 * ADR-025: /finance/balance WC-lite (not double-entry BS) + disclaimer.
 */
class WorkingCapitalBalanceTest extends SaasTestCase
{
    public function test_start_and_pro_cannot_open_balance(): void
    {
        foreach (['start', 'pro'] as $plan) {
            $fixture = $this->seedStaffTenant($plan);
            $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

            $this->get(route('finance.balance'))->assertForbidden();
        }
    }

    public function test_enterprise_balance_exposes_working_capital_lite_disclaimer(): void
    {
        $fixture = $this->seedStaffTenant('enterprise');
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

        ManagementBankAccount::query()->create([
            'bank_name' => 'Balance Bank',
            'account_number' => 'B'.uniqid(),
            'account_mask' => '****1111',
            'currency' => 'RUB',
            'is_active' => true,
            'sort_order' => 10,
            'opening_balance' => 100000,
            'opening_balance_as_of' => now()->subMonth()->toDateString(),
            'opening_balance_source' => 'manual',
        ]);

        $this->seedOpenPair($fixture['tenant'], $fixture['staff'], 40000, 25000);

        $this->get(route('finance.balance'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Finance/WorkingCapital/Balance')
                ->where('balance.report_kind', 'working_capital_lite')
                ->where('balance.disclaimer', fn ($v): bool => is_string($v) && str_contains($v, 'не бухгалтерский баланс'))
                ->where('balance.pnl_recognition_basis', 'execution_or_closed')
                ->where('balance.snapshot.cash', fn ($v): bool => (float) $v === 100000.0)
                ->where('balance.snapshot.receivables', fn ($v): bool => (float) $v === 40000.0)
                ->where('balance.snapshot.payables', fn ($v): bool => (float) $v === 25000.0)
                ->where('balance.snapshot.own_money_estimate', fn ($v): bool => (float) $v === 140000.0)
                ->where('balance.snapshot.borrowed_estimate', fn ($v): bool => (float) $v === 25000.0)
                ->etc());
    }

    public function test_balance_without_ua_role_is_forbidden(): void
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
            'name' => 'No UA',
            'email' => 'bal-noua-'.$fixture['tenant']->slug.'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
            'can_management_accounting' => false,
        ]);

        $this->actingAsTenant($fixture['tenant'], $manager);

        $this->get(route('finance.balance'))->assertForbidden();
    }

    public function test_balance_aggregates_do_not_leak_across_tenants(): void
    {
        $tenantA = $this->seedStaffTenant('enterprise', 'ba');
        $tenantB = $this->seedStaffTenant('enterprise', 'bb');

        $this->actingAsTenant($tenantA['tenant'], $tenantA['staff']);
        $this->seedOpenPair($tenantA['tenant'], $tenantA['staff'], 900000, 800000);

        $this->actingAsTenant($tenantB['tenant'], $tenantB['staff']);
        $this->seedOpenPair($tenantB['tenant'], $tenantB['staff'], 15000, 7000);

        $this->get(route('finance.balance'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('balance.snapshot.receivables', fn ($v): bool => (float) $v === 15000.0)
                ->where('balance.snapshot.payables', fn ($v): bool => (float) $v === 7000.0)
                ->etc());
    }

    /**
     * @return array{tenant: Tenant, staff: User}
     */
    private function seedStaffTenant(string $plan, string $suffix = ''): array
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'bal-'.$plan.($suffix !== '' ? '-'.$suffix : '').'-'.uniqid(),
            'name' => 'Balance '.$plan,
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
            'name' => 'Balance Staff',
            'email' => 'bal-'.$tenant->slug.'@saas.local',
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

    private function seedOpenPair(Tenant $tenant, User $manager, float $ar, float $ap): void
    {
        TenantContext::set($tenant);

        $customer = Contractor::query()->create([
            'name' => 'AR '.uniqid(),
            'type' => 'customer',
            'is_active' => true,
        ]);
        $carrier = Contractor::query()->create([
            'name' => 'AP '.uniqid(),
            'type' => 'carrier',
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'tenant_id' => $tenant->id,
            'order_number' => 'BAL-'.uniqid(),
            'company_code' => 'BL',
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
        ]);
    }
}
