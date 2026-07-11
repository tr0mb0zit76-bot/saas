<?php

namespace Tests\Feature\Saas;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Tests\SaasTestCase;

class PlatformTenantManagementTest extends SaasTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['saas.platform_admin_emails' => ['platform-admin@saas.local']]);
    }

    public function test_non_platform_admin_cannot_access_tenant_registry(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'plat-deny-'.uniqid(),
            'name' => 'Deny',
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

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => 'Regular Admin',
            'email' => 'regular-'.uniqid().'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(false);
        config(['saas.default_tenant_slug' => $tenant->slug]);

        $this->actingAs($user)->get('/platform/tenants')->assertForbidden();
    }

    public function test_platform_admin_can_list_and_create_tenants(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'plat-host-'.uniqid(),
            'name' => 'Host',
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

        $admin = User::query()->create([
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

        $this->actingAs($admin)->get('/platform/tenants')->assertOk();

        $slug = 'newco-'.uniqid();

        $this->actingAs($admin)->post('/platform/tenants', [
            'slug' => $slug,
            'name' => 'New Company',
            'status' => 'trial',
            'plan' => 'start',
        ])->assertRedirect(route('platform.tenants.index'));

        TenantContext::bypass(true);
        $this->assertDatabaseHas('tenants', ['slug' => $slug, 'plan' => 'start', 'status' => 'trial']);
        TenantContext::bypass(false);
    }
}
