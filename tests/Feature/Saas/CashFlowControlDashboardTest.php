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
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\SaasTestCase;

/**
 * ADR-024 P1: cashflow control dashboard on /finance?section=cashflow.
 */
class CashFlowControlDashboardTest extends SaasTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_pro_cashflow_dashboard_is_schedules_only_without_thin_cash(): void
    {
        $fixture = $this->seedStaffTenant('pro');
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);
        $this->seedSchedulePair($fixture['tenant'], $fixture['staff'], ownCompanyName: 'ООО Про');

        $this->get(route('finance.index', ['section' => 'cashflow']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Finance/Index')
                ->has('cashflow_dashboard')
                ->where('cashflow_dashboard.mode', 'schedules_only')
                ->where('cashflow_dashboard.lenses.cash_now.available', false)
                ->where('cashflow_dashboard.lenses.expectations.available', true)
                ->where('cashflow_dashboard.lenses.management_pnl.stub', true)
                ->has('cashflow_dashboard.period_options', 4)
                ->where('cashflow_dashboard.gauges.incomes.plan', fn ($v): bool => (float) $v === 100000.0)
                ->where('cashflow_dashboard.gauges.incomes.fact', fn ($v): bool => (float) $v === 40000.0)
                ->where('cashflow_dashboard.gauges.expenses.plan', fn ($v): bool => (float) $v === 70000.0)
                ->has('cashflow_dashboard.by_direction')
                ->etc());
    }

    public function test_enterprise_cashflow_dashboard_exposes_thin_cash_lens(): void
    {
        $fixture = $this->seedStaffTenant('enterprise');
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

        ManagementBankAccount::query()->create([
            'bank_name' => 'Cash Bank',
            'account_number' => 'E'.uniqid(),
            'account_mask' => '****9999',
            'currency' => 'RUB',
            'is_active' => true,
            'sort_order' => 10,
            'opening_balance' => 250000,
            'opening_balance_as_of' => now()->subMonth()->toDateString(),
            'opening_balance_source' => 'manual',
        ]);

        $this->seedSchedulePair($fixture['tenant'], $fixture['staff'], ownCompanyName: 'ООО Enterprise');

        $this->get(route('finance.index', ['section' => 'cashflow']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Finance/Index')
                ->where('cashflow_dashboard.mode', 'schedules_plus_bank')
                ->where('cashflow_dashboard.lenses.cash_now.available', true)
                ->where('cashflow_dashboard.lenses.cash_now.value.balance_now', fn ($v): bool => (float) $v === 250000.0)
                ->where('cashflow_dashboard.projection.cash_now', fn ($v): bool => (float) $v === 250000.0)
                ->etc());
    }

    public function test_dashboard_aggregates_do_not_leak_across_tenants(): void
    {
        $tenantA = $this->seedStaffTenant('pro', 'cfa');
        $tenantB = $this->seedStaffTenant('pro', 'cfb');

        $this->actingAsTenant($tenantA['tenant'], $tenantA['staff']);
        $this->seedSchedulePair($tenantA['tenant'], $tenantA['staff'], ownCompanyName: 'Secret A Co', incomeAmount: 999999);

        $this->actingAsTenant($tenantB['tenant'], $tenantB['staff']);
        $this->seedSchedulePair($tenantB['tenant'], $tenantB['staff'], ownCompanyName: 'Visible B Co', incomeAmount: 15000, incomePaid: 0);

        $response = $this->get(route('finance.index', ['section' => 'cashflow']))
            ->assertOk();

        $response->assertInertia(fn (Assert $page): Assert => $page
            ->where('cashflow_dashboard.gauges.incomes.plan', fn ($v): bool => (float) $v === 15000.0)
            ->where('cashflow_dashboard.by_direction', function ($rows): bool {
                $labels = collect($rows)->pluck('label')->all();

                return in_array('Visible B Co', $labels, true)
                    && ! in_array('Secret A Co', $labels, true);
            }));
    }

    public function test_start_plan_without_payment_schedules_feature_still_redirects_or_gates_hub(): void
    {
        $fixture = $this->seedStaffTenant('start');
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

        $this->assertFalse($fixture['tenant']->featureEnabled('payment_schedules'));

        // Hub is visibility-gated; Start admin may open overview, but dashboard props only on cashflow + schedules access.
        $this->get(route('finance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Finance/Index')
                ->missing('cashflow_dashboard'));
    }

    /**
     * @return array{tenant: Tenant, staff: User}
     */
    private function seedStaffTenant(string $plan, string $suffix = ''): array
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'cf-'.$plan.($suffix !== '' ? '-'.$suffix : '').'-'.uniqid(),
            'name' => 'Cashflow '.$plan,
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
            'name' => 'Cashflow Staff',
            'email' => 'cf-'.$tenant->slug.'@saas.local',
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

    private function seedSchedulePair(
        Tenant $tenant,
        User $staff,
        string $ownCompanyName,
        float $incomeAmount = 100000,
        float $incomePaid = 40000,
        float $expenseAmount = 70000,
    ): void {
        TenantContext::set($tenant);

        $ownCompany = Contractor::query()->create([
            'name' => $ownCompanyName,
            'type' => 'both',
            'is_own_company' => true,
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'tenant_id' => $tenant->id,
            'order_number' => 'CF-'.uniqid(),
            'company_code' => 'CF',
            'manager_id' => $staff->id,
            'own_company_id' => $ownCompany->id,
            'status' => 'payment',
            'is_active' => true,
            'is_international_transport' => false,
            'order_date' => now()->toDateString(),
        ]);

        PaymentSchedule::query()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => $incomeAmount,
            'paid_amount' => $incomePaid,
            'remaining_amount' => max(0, $incomeAmount - $incomePaid),
            'planned_date' => '2026-11-10',
            'status' => $incomePaid >= $incomeAmount ? 'paid' : 'pending',
        ]);

        PaymentSchedule::query()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => $expenseAmount,
            'paid_amount' => 0,
            'remaining_amount' => $expenseAmount,
            'planned_date' => '2026-11-12',
            'status' => 'pending',
        ]);
    }
}
