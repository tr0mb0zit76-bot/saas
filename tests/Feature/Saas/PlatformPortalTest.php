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

class PlatformPortalTest extends SaasTestCase
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
            'slug' => 'portal-host-'.uniqid(),
            'name' => 'Portal Host',
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
    }

    public function test_guest_is_redirected_to_platform_login(): void
    {
        $this->get($this->platformUrl('/'))
            ->assertRedirect($this->platformUrl('/login'));
    }

    public function test_platform_admin_can_log_in_via_platform_portal(): void
    {
        $this->post($this->platformUrl('/login'), [
            'email' => 'platform-admin@saas.local',
            'password' => 'password',
        ])->assertRedirect(route('platform.dashboard'));

        $this->assertAuthenticatedAs($this->platformAdmin);
    }

    public function test_non_platform_email_cannot_log_in_via_platform_portal(): void
    {
        $this->post($this->platformUrl('/login'), [
            'email' => 'someone@saas.local',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_legacy_crm_platform_path_redirects_to_platform_portal(): void
    {
        $this->get('/platform/tenants')
            ->assertRedirect(PlatformHost::url('/tenants'));
    }

    private function platformUrl(string $path = '/'): string
    {
        return PlatformHost::url($path);
    }
}
