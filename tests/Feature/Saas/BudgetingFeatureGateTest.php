<?php

namespace Tests\Feature\Saas;

use App\Models\BudgetOpexArticle;
use App\Models\BudgetScenario;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\SaasTestCase;

/**
 * ADR-020 P0: budgeting is Enterprise-gated; OPEX mutations are tenant-scoped.
 */
class BudgetingFeatureGateTest extends SaasTestCase
{
    public function test_start_plan_blocks_budgeting_index_and_mutations(): void
    {
        $fixture = $this->seedStaffTenant('start');
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

        $this->assertFalse($fixture['tenant']->featureEnabled('budgeting'));

        $this->get(route('budgeting.index'))->assertForbidden();

        $this->patch(route('budgeting.scenario.update'), [
            'inputs' => ['manager_count' => 2, 'horizon_months' => 12],
        ])->assertForbidden();

        $this->post(route('budgeting.opex-articles.store'), [
            'name' => 'Офис',
            'cost_type' => BudgetOpexArticle::COST_FIXED_MONTHLY,
            'amount_basis' => BudgetOpexArticle::AMOUNT_BASIS_TOTAL,
            'amount_monthly' => 10000,
        ])->assertForbidden();
    }

    public function test_enterprise_allows_budgeting_index(): void
    {
        $fixture = $this->seedStaffTenant('enterprise');
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

        $this->assertTrue($fixture['tenant']->featureEnabled('budgeting'));

        $this->get(route('budgeting.index'))->assertOk();
    }

    public function test_opex_update_and_destroy_are_cross_tenant_404(): void
    {
        $tenantA = $this->seedStaffTenant('enterprise', 'a');
        $tenantB = $this->seedStaffTenant('enterprise', 'b');

        $this->actingAsTenant($tenantA['tenant'], $tenantA['staff']);

        BudgetScenario::query()->create([
            'name' => 'A',
            'inputs' => ['manager_count' => 3, 'horizon_months' => 12],
        ]);

        $articleA = BudgetOpexArticle::query()->create([
            'name' => 'Оклады менеджеров',
            'cost_type' => BudgetOpexArticle::COST_FIXED_MONTHLY,
            'amount_basis' => BudgetOpexArticle::AMOUNT_BASIS_PER_HEAD,
            'amount_monthly' => 150000,
            'sort_order' => 10,
        ]);

        $this->actingAsTenant($tenantB['tenant'], $tenantB['staff']);

        $this->patch(route('budgeting.opex-articles.update', $articleA), [
            'cost_type' => BudgetOpexArticle::COST_FIXED_MONTHLY,
            'amount_basis' => BudgetOpexArticle::AMOUNT_BASIS_PER_HEAD,
            'amount_monthly' => 1,
        ])->assertNotFound();

        $this->delete(route('budgeting.opex-articles.destroy', $articleA))
            ->assertNotFound();

        $this->actingAsTenant($tenantA['tenant'], $tenantA['staff']);
        $this->assertDatabaseHas('budget_opex_articles', [
            'id' => $articleA->id,
            'amount_monthly' => 150000,
        ]);
    }

    public function test_index_does_not_leak_foreign_opex_articles(): void
    {
        $tenantA = $this->seedStaffTenant('enterprise', 'ia');
        $tenantB = $this->seedStaffTenant('enterprise', 'ib');

        $this->actingAsTenant($tenantA['tenant'], $tenantA['staff']);
        BudgetOpexArticle::query()->create([
            'name' => 'Secret A payroll',
            'cost_type' => BudgetOpexArticle::COST_FIXED_MONTHLY,
            'amount_basis' => BudgetOpexArticle::AMOUNT_BASIS_PER_HEAD,
            'amount_monthly' => 150000,
            'sort_order' => 10,
        ]);

        $this->actingAsTenant($tenantB['tenant'], $tenantB['staff']);

        $this->get(route('budgeting.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page): Assert {
                return $page
                    ->component('Budgeting/Index')
                    ->has('opex_articles')
                    ->where('opex_articles', function ($articles): bool {
                        return collect($articles)->pluck('name')->doesntContain('Secret A payroll');
                    });
            });
    }

    /**
     * @return array{tenant: Tenant, staff: User}
     */
    private function seedStaffTenant(string $plan, string $suffix = ''): array
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'budget-'.$plan.($suffix !== '' ? '-'.$suffix : '').'-'.uniqid(),
            'name' => 'Budget '.$plan,
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
            'name' => 'Budget Staff',
            'email' => 'budget-'.$tenant->slug.'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
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
