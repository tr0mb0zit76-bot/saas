<?php

namespace Tests\Feature\Saas;

use App\Models\BudgetOpexArticle;
use App\Models\BudgetScenario;
use App\Models\ManagementBankAccount;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\SaasTestCase;

/**
 * ADR-023 P0: workbook shell, soft-map, thin cash isolation, feature gates.
 */
class WorkbookPlanningControlTest extends SaasTestCase
{
    public function test_budgeting_index_exposes_workbook_and_cvp_props(): void
    {
        $fixture = $this->seedStaffTenant('enterprise');
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

        $this->get(route('budgeting.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Budgeting/Index')
                ->has('workbook')
                ->has('cvp')
                ->has('opex_articles')
                ->where('cvp.disclaimer', fn ($text): bool => is_string($text) && str_contains($text, 'не касса')));
    }

    public function test_opex_store_requires_soft_map_same_tenant_category(): void
    {
        $tenantA = $this->seedStaffTenant('enterprise', 'sma');
        $tenantB = $this->seedStaffTenant('enterprise', 'smb');

        $this->actingAsTenant($tenantB['tenant'], $tenantB['staff']);
        $foreignCategory = ManagementExpenseCategory::query()->create([
            'code' => 'foreign_opex_'.uniqid(),
            'name' => 'Foreign',
            'kind' => 'expense',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->actingAsTenant($tenantA['tenant'], $tenantA['staff']);

        $this->post(route('budgeting.opex-articles.store'), [
            'name' => 'Аренда',
            'cost_type' => BudgetOpexArticle::COST_FIXED_MONTHLY,
            'amount_basis' => BudgetOpexArticle::AMOUNT_BASIS_TOTAL,
            'amount_monthly' => 50000,
            'management_expense_category_id' => $foreignCategory->id,
        ])->assertSessionHasErrors('management_expense_category_id');

        $localCategory = ManagementExpenseCategory::query()->create([
            'code' => 'local_opex_'.uniqid(),
            'name' => 'Local rent',
            'kind' => 'expense',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->post(route('budgeting.opex-articles.store'), [
            'name' => 'Аренда',
            'cost_type' => BudgetOpexArticle::COST_FIXED_MONTHLY,
            'amount_basis' => BudgetOpexArticle::AMOUNT_BASIS_TOTAL,
            'amount_monthly' => 50000,
            'management_expense_category_id' => $localCategory->id,
        ])->assertRedirect(route('budgeting.index'));

        $this->assertDatabaseHas('budget_opex_articles', [
            'tenant_id' => $tenantA['tenant']->id,
            'name' => 'Аренда',
            'management_expense_category_id' => $localCategory->id,
        ]);
    }

    public function test_management_accounting_exposes_plan_source_and_thin_cash(): void
    {
        $fixture = $this->seedStaffTenant('enterprise');
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

        $this->get(route('finance.management-accounting.index', ['tab' => 'cash']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Finance/ManagementAccounting/Index')
                ->has('workbook')
                ->has('thin_cash')
                ->has('analytics.plan_source')
                ->has('analytics.soft_map_ready')
                ->where('filters.tab', 'cash')
                ->where('thin_cash.disclaimer', fn ($text): bool => is_string($text) && str_contains($text, 'Не полный ДДС')));
    }

    public function test_second_tenant_gets_own_expense_tree_in_pivot_report(): void
    {
        $tenantA = $this->seedStaffTenant('enterprise', 'piva');
        $this->actingAsTenant($tenantA['tenant'], $tenantA['staff']);
        $this->get(route('finance.management-accounting.index'))->assertOk();

        $codesA = ManagementExpenseCategory::query()->orderBy('code')->pluck('code')->all();
        $this->assertContains('group_expense', $codesA);
        $this->assertContains('group_income', $codesA);

        $tenantB = $this->seedStaffTenant('enterprise', 'pivb');
        $this->actingAsTenant($tenantB['tenant'], $tenantB['staff']);

        $this->assertSame(0, ManagementExpenseCategory::query()->count());

        $this->get(route('finance.management-accounting.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Finance/ManagementAccounting/Index')
                ->where('analytics.pivot.rows', function ($rows): bool {
                    $codes = collect($rows)->pluck('code')->all();

                    return in_array('group_expense', $codes, true)
                        && in_array('group_income', $codes, true)
                        && in_array('group_cost', $codes, true)
                        && in_array('gross_margin', $codes, true);
                }));

        $this->assertDatabaseHas('management_expense_categories', [
            'tenant_id' => $tenantB['tenant']->id,
            'code' => 'group_expense',
        ]);
        $this->assertDatabaseHas('management_expense_categories', [
            'tenant_id' => $tenantA['tenant']->id,
            'code' => 'group_expense',
        ]);
    }

    public function test_thin_cash_balance_does_not_leak_cross_tenant(): void
    {
        $tenantA = $this->seedStaffTenant('enterprise', 'casha');
        $tenantB = $this->seedStaffTenant('enterprise', 'cashb');

        $this->actingAsTenant($tenantA['tenant'], $tenantA['staff']);
        $accountA = ManagementBankAccount::query()->create([
            'bank_name' => 'Bank A',
            'account_number' => 'A'.uniqid(),
            'account_mask' => '****1111',
            'currency' => 'RUB',
            'is_active' => true,
            'sort_order' => 10,
            'opening_balance' => 100000,
            'opening_balance_as_of' => now()->subMonth()->toDateString(),
            'opening_balance_source' => 'manual',
        ]);
        ManagementStatementLine::query()->create([
            'import_id' => null,
            'bank_account_id' => $accountA->id,
            'line_hash' => hash('sha256', 'a-secret'),
            'operation_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => 25000,
            'currency' => 'RUB',
            'description' => 'Secret inflow A',
            'status' => 'pending',
            'source' => 'import',
        ]);

        $this->actingAsTenant($tenantB['tenant'], $tenantB['staff']);
        $accountB = ManagementBankAccount::query()->create([
            'bank_name' => 'Bank B',
            'account_number' => 'B'.uniqid(),
            'account_mask' => '****2222',
            'currency' => 'RUB',
            'is_active' => true,
            'sort_order' => 10,
            'opening_balance' => 1000,
            'opening_balance_as_of' => now()->subMonth()->toDateString(),
            'opening_balance_source' => 'manual',
        ]);

        $response = $this->get(route('finance.management-accounting.index', ['tab' => 'cash']))
            ->assertOk();

        $response->assertInertia(fn (Assert $page): Assert => $page
            ->where('thin_cash.balance_now', fn ($value): bool => (float) $value === 1000.0)
            ->where('thin_cash.opening_balance', fn ($value): bool => (float) $value === 1000.0)
            ->where('thin_cash.accounts', function ($accounts) use ($accountA, $accountB): bool {
                $ids = collect($accounts)->pluck('id');

                return $ids->contains($accountB->id) && ! $ids->contains($accountA->id);
            }));
    }

    public function test_opening_balance_mutation_is_feature_gated_on_start_plan(): void
    {
        $fixture = $this->seedStaffTenant('start');
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

        $this->assertFalse($fixture['tenant']->featureEnabled('management_accounting'));

        $this->patch(route('finance.management-accounting.thin-cash.opening-balance'), [
            'bank_account_id' => 1,
            'opening_balance' => 5000,
            'opening_balance_source' => 'manual',
        ])->assertForbidden();
    }

    public function test_min_icp_seed_creates_opex_on_first_budgeting_enter(): void
    {
        $fixture = $this->seedStaffTenant('enterprise', 'seed');
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

        BudgetScenario::query()->create([
            'name' => 'Основной',
            'inputs' => ['manager_count' => 3, 'horizon_months' => 12],
        ]);

        $this->assertSame(0, BudgetOpexArticle::query()->count());

        $this->get(route('budgeting.index'))->assertOk();

        $this->assertGreaterThanOrEqual(6, BudgetOpexArticle::query()->count());
    }

    /**
     * @return array{tenant: Tenant, staff: User}
     */
    private function seedStaffTenant(string $plan, string $suffix = ''): array
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'wb-'.$plan.($suffix !== '' ? '-'.$suffix : '').'-'.uniqid(),
            'name' => 'Workbook '.$plan,
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
            'name' => 'Workbook Staff',
            'email' => 'wb-'.$tenant->slug.'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
            'can_management_accounting' => true,
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
}
