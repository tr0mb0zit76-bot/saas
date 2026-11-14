<?php

namespace Tests\Feature\Saas;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Tests\SaasTestCase;

/**
 * ADR-019: lead customs / TN VED paths require import_cost; transport calculate stays open.
 */
class LeadPrecalculationFeatureGateTest extends SaasTestCase
{
    public function test_start_plan_blocks_lead_tn_ved_search(): void
    {
        $fixture = $this->seedStaffTenant('start');
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

        $this->assertFalse($fixture['tenant']->featureEnabled('import_cost'));

        $this->getJson(route('leads.precalculation.tn-ved.search', ['q' => '8429']))
            ->assertForbidden();
    }

    public function test_start_plan_blocks_calculate_with_goods_lines(): void
    {
        $fixture = $this->seedStaffTenant('start');
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

        $this->postJson(route('leads.precalculation.calculate'), [
            'goods_lines' => [[
                'id' => 'goods_1',
                'tn_ved_code' => '8429.51',
                'invoice_amount' => 1000,
                'currency' => 'RUB',
            ]],
            'service_lines' => [],
        ])->assertForbidden();
    }

    public function test_start_plan_allows_transport_only_calculate(): void
    {
        $fixture = $this->seedStaffTenant('start');
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

        $response = $this->postJson(route('leads.precalculation.calculate'), [
            'goods_lines' => [],
            'service_lines' => [[
                'id' => 'service_1',
                'kind' => 'logistics',
                'title' => 'Логистика · Плечо 1',
                'stage' => 'leg_1',
                'amount' => 85000,
                'currency' => 'RUB',
            ]],
        ])->assertOk();

        $this->assertSame(85000.0, (float) $response->json('computed.services_total'));
        $this->assertSame(0.0, (float) $response->json('computed.goods_total'));
        $this->assertSame(85000.0, (float) $response->json('computed.grand_total'));
    }

    public function test_enterprise_import_cost_override_false_blocks_customs_paths(): void
    {
        $fixture = $this->seedStaffTenant('enterprise', [
            'features' => ['import_cost' => false],
        ]);
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

        $this->assertFalse($fixture['tenant']->fresh()->featureEnabled('import_cost'));
        $this->assertNotContains('import_cost', $fixture['tenant']->fresh()->enabledFeatures());

        $this->getJson(route('leads.precalculation.tn-ved.search', ['q' => '8429']))
            ->assertForbidden();

        $this->postJson(route('leads.precalculation.calculate'), [
            'goods_lines' => [[
                'id' => 'goods_1',
                'tn_ved_code' => '8429.51',
                'invoice_amount' => 1000,
                'currency' => 'RUB',
            ]],
            'service_lines' => [],
        ])->assertForbidden();
    }

    public function test_start_and_pro_plans_do_not_include_import_cost(): void
    {
        foreach (['start', 'pro'] as $plan) {
            $fixture = $this->seedStaffTenant($plan);
            $this->assertFalse(
                $fixture['tenant']->featureEnabled('import_cost'),
                "Plan {$plan} must not enable import_cost for ADR-019 pilot.",
            );
            $this->assertNotContains('import_cost', $fixture['tenant']->enabledFeatures());
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{tenant: Tenant, staff: User}
     */
    private function seedStaffTenant(string $plan, array $settings = []): array
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'precalc-'.$plan.'-'.uniqid(),
            'name' => 'Precalc '.$plan,
            'status' => 'active',
            'plan' => $plan,
            'settings' => array_merge(
                ['onboarding' => ['completed_at' => now()->toIso8601String()]],
                $settings,
            ),
        ]);

        $staffRole = Role::query()->firstOrCreate(
            ['name' => 'admin', 'tenant_id' => $tenant->id],
            [
                'display_name' => 'Admin',
                'permissions' => RoleAccess::permissionKeys(),
                'visibility_areas' => RoleAccess::defaultVisibilityAreas('admin'),
                'visibility_scopes' => RoleAccess::defaultVisibilityScopes('admin'),
            ],
        );

        $staff = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $staffRole->id,
            'name' => 'Staff',
            'email' => 'staff-'.$tenant->slug.'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(false);

        return [
            'tenant' => $tenant,
            'staff' => $staff,
        ];
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        config(['saas.default_tenant_slug' => $tenant->slug]);
        TenantContext::set($tenant);
        $this->actingAs($user);

        return $this;
    }
}
