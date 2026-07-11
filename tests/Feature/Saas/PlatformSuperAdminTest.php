<?php

namespace Tests\Feature\Saas;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PlatformHost;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Tests\SaasTestCase;

class PlatformSuperAdminTest extends SaasTestCase
{
    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.platform_domain' => 'platform.test',
            'saas.platform_admin_emails' => ['platform-admin@saas.local'],
        ]);

        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'plat-super-'.uniqid(),
            'name' => 'Platform Host',
            'status' => 'active',
            'plan' => 'pro',
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
        config(['saas.default_tenant_slug' => $tenant->slug]);
    }

    public function test_platform_admin_can_open_super_admin_sections(): void
    {
        $this->actingAs($this->platformAdmin)->get($this->platformUrl('/'))->assertOk();
        $this->actingAs($this->platformAdmin)->get($this->platformUrl('/plans'))->assertOk();
        $this->actingAs($this->platformAdmin)->get($this->platformUrl('/tenants'))->assertOk();
    }

    public function test_platform_admin_can_override_tenant_features(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'override-'.uniqid(),
            'name' => 'Override Co',
            'status' => 'active',
            'plan' => 'start',
        ]);

        TenantContext::bypass(false);

        $this->actingAs($this->platformAdmin)
            ->patch(route('platform.tenants.features.update', $tenant), [
                'features' => ['mail' => true],
            ])
            ->assertRedirect(route('platform.tenants.features', $tenant));

        TenantContext::bypass(true);
        $tenant->refresh();
        TenantContext::bypass(false);

        $this->assertTrue($tenant->featureEnabled('mail'));
    }

    public function test_feature_override_grants_route_access_on_start_plan(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'mail-override-'.uniqid(),
            'name' => 'Mail Override',
            'status' => 'active',
            'plan' => 'start',
            'settings' => ['features' => ['mail' => true]],
        ]);

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'admin',
            'display_name' => 'Admin',
            'permissions' => RoleAccess::permissionKeys(),
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('admin'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('admin'),
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => 'Tenant Admin',
            'email' => 'tenant-mail-'.uniqid().'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(false);
        config(['saas.default_tenant_slug' => $tenant->slug]);

        $this->actingAs($user)->get('/mail')->assertOk();
    }

    private function platformUrl(string $path = '/'): string
    {
        return PlatformHost::url($path);
    }
}
