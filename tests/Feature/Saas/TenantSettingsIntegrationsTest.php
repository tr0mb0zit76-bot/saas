<?php

namespace Tests\Feature\Saas;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\SaasTestCase;

class TenantSettingsIntegrationsTest extends SaasTestCase
{
    public function test_admin_with_integrations_feature_sees_settings_page(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(withIntegrations: true);

        $this->actingAs($admin)
            ->get(route('settings.integrations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Integrations/Index')
                ->where('feature_enabled', true)
                ->where('can_manage', true)
                ->has('catalog', 6));
    }

    public function test_page_shows_lock_when_integrations_feature_off(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(withIntegrations: false);

        $this->actingAs($admin)
            ->get(route('settings.integrations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Integrations/Index')
                ->where('feature_enabled', false)
                ->where('can_manage', false));
    }

    public function test_admin_can_bind_self_serve_stub_connector(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(withIntegrations: true);

        $this->actingAs($admin)
            ->post(route('settings.integrations.store'), [
                'adapter_key' => 't_bank',
                'enabled' => true,
                'secrets' => ['token' => 'test-token'],
            ])
            ->assertRedirect(route('settings.integrations.index'));

        $tenant->refresh();
        $list = data_get($tenant->settings, 'integrations', []);
        $this->assertIsArray($list);
        $this->assertCount(1, $list);
        $this->assertSame('t_bank', $list[0]['adapter_key']);
        $this->assertSame('tenant', $list[0]['managed_by']);
        $this->assertStringStartsWith('enc:', (string) ($list[0]['secrets']['token'] ?? ''));
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function makeTenantAdmin(bool $withIntegrations): array
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'int-'.uniqid(),
            'name' => 'Integrations Tenant',
            'status' => 'active',
            'plan' => $withIntegrations ? 'enterprise' : 'start',
            'settings' => [
                'onboarding' => ['completed_at' => now()->toIso8601String()],
                'features' => $withIntegrations
                    ? ['integrations' => true]
                    : ['integrations' => false],
            ],
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
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(false);
        TenantContext::set($tenant);
        config(['saas.default_tenant_slug' => $tenant->slug]);

        return [$tenant, $admin];
    }
}
