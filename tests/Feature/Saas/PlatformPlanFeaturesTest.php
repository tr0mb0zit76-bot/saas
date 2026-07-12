<?php

namespace Tests\Feature\Saas;

use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PlatformHost;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Tests\SaasTestCase;

class PlatformPlanFeaturesTest extends SaasTestCase
{
    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.platform_domain' => 'platform.test',
            'saas.platform_admin_emails' => ['platform-admin@saas.local'],
        ]);

        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);

        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'plan-host-'.uniqid(),
            'name' => 'Plan Host',
            'status' => 'active',
            'plan' => 'start',
        ]);

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'admin',
            'display_name' => 'Admin',
            'permissions' => RoleAccess::permissionKeys(),
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('admin'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('admin'),
        ]);

        $this->platformAdmin = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => 'Platform Admin',
            'email' => 'platform-admin@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(false);
    }

    public function test_platform_admin_can_edit_plan_features_in_database(): void
    {
        $this->actingAs($this->platformAdmin)
            ->get($this->platformUrl('/plans/start/features'))
            ->assertOk();

        $this->actingAs($this->platformAdmin)
            ->patch($this->platformUrl('/plans/start/features'), [
                'features' => [
                    'leads' => true,
                    'orders' => true,
                    'mail' => true,
                ],
                'limits' => [
                    'users' => 5,
                    'orders_per_month' => 200,
                    'storage_mb' => 2048,
                ],
            ])
            ->assertRedirect(route('platform.plans.edit', 'start'));

        $plan = SubscriptionPlan::query()->where('key', 'start')->first();

        $this->assertNotNull($plan);
        $this->assertContains('mail', $plan->featuresList());
        $this->assertNotContains('documents', $plan->featuresList());
    }

    public function test_tenant_plan_features_resolve_from_database(): void
    {
        SubscriptionPlan::query()->where('key', 'start')->update([
            'features' => ['leads', 'orders', 'contractors', 'tasks', 'grid_views', 'mail'],
        ]);

        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'plan-tenant-'.uniqid(),
            'name' => 'Plan Tenant',
            'status' => 'active',
            'plan' => 'start',
        ]);

        TenantContext::bypass(false);

        $this->assertTrue($tenant->fresh()->featureEnabled('mail'));
        $this->assertFalse($tenant->fresh()->featureEnabled('documents'));
    }

    public function test_platform_admin_can_edit_plan_limits_in_database(): void
    {
        $this->actingAs($this->platformAdmin)
            ->patch($this->platformUrl('/plans/start/features'), [
                'features' => [
                    'leads' => true,
                    'orders' => true,
                    'contractors' => true,
                    'tasks' => true,
                    'grid_views' => true,
                ],
                'limits' => [
                    'users' => 3,
                    'orders_per_month' => 50,
                    'storage_mb' => 512,
                ],
            ])
            ->assertRedirect(route('platform.plans.edit', 'start'));

        $plan = SubscriptionPlan::query()->where('key', 'start')->first();

        $this->assertNotNull($plan);
        $this->assertSame(3, $plan->limitsMap()['users']);
        $this->assertSame(50, $plan->limitsMap()['orders_per_month']);
        $this->assertSame(512, $plan->limitsMap()['storage_mb']);

        $this->assertDatabaseHas('tenant_audit_logs', [
            'action' => 'plan.updated',
            'entity_type' => 'subscription_plan',
            'entity_id' => $plan->id,
            'user_id' => $this->platformAdmin->id,
        ]);
    }

    public function test_tenant_plan_limits_resolve_from_database(): void
    {
        SubscriptionPlan::query()->where('key', 'start')->update([
            'limits' => [
                'users' => 2,
                'orders_per_month' => 10,
                'storage_mb' => 100,
            ],
        ]);

        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'limit-tenant-'.uniqid(),
            'name' => 'Limit Tenant',
            'status' => 'active',
            'plan' => 'start',
        ]);

        TenantContext::bypass(false);

        $limits = $tenant->fresh()->planLimits();

        $this->assertSame(2, $limits['users']);
        $this->assertSame(10, $limits['orders_per_month']);
        $this->assertSame(100, $limits['storage_mb']);
    }

    private function platformUrl(string $path = '/'): string
    {
        return PlatformHost::url($path);
    }
}
