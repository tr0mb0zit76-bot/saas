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

    public function test_non_admin_cannot_store_connector(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(withIntegrations: true);
        $manager = $this->makeUser($tenant, 'manager');

        $this->actingAs($manager)
            ->post(route('settings.integrations.store'), [
                'adapter_key' => 't_bank',
                'enabled' => true,
                'secrets' => ['token' => 'x'],
            ])
            ->assertForbidden();
    }

    public function test_store_forbidden_when_integrations_feature_off(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(withIntegrations: false);

        $this->actingAs($admin)
            ->post(route('settings.integrations.store'), [
                'adapter_key' => 't_bank',
                'enabled' => true,
                'secrets' => ['token' => 'x'],
            ])
            ->assertForbidden();
    }

    public function test_store_rejects_non_catalog_adapter_key(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(withIntegrations: true);

        $this->actingAs($admin)
            ->post(route('settings.integrations.store'), [
                'adapter_key' => 'aa_one_c_bp',
                'enabled' => true,
            ])
            ->assertSessionHasErrors('adapter_key');
    }

    public function test_index_inertia_omits_secret_values(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(withIntegrations: true);

        $this->actingAs($admin)
            ->post(route('settings.integrations.store'), [
                'adapter_key' => 't_bank',
                'enabled' => true,
                'secrets' => ['token' => 'super-secret-token-value'],
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('settings.integrations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Integrations/Index')
                ->has('connectors', 1)
                ->where('connectors.0.has_secrets', true)
                ->missing('connectors.0.secrets'))
            ->assertDontSee('super-secret-token-value');
    }

    public function test_tenant_cannot_mutate_platform_managed_connector(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(withIntegrations: true);

        $connectorId = (string) \Illuminate\Support\Str::uuid();
        $tenant->settings = array_merge($tenant->settings ?? [], [
            'integrations' => [[
                'id' => $connectorId,
                'adapter_key' => 'one_c_bp',
                'enabled' => true,
                'managed_by' => 'platform',
                'endpoints' => ['base_url' => 'https://example.com/odata'],
                'mapping' => [],
                'secrets' => ['username' => 'enc:x', 'password' => 'enc:y'],
            ]],
        ]);
        $tenant->save();

        $this->actingAs($admin)
            ->put(route('settings.integrations.update', $connectorId), ['enabled' => false])
            ->assertRedirect();

        $tenant->refresh();
        $row = collect(data_get($tenant->settings, 'integrations', []))
            ->firstWhere('id', $connectorId);
        $this->assertIsArray($row);
        $this->assertTrue((bool) ($row['enabled'] ?? false));
        $this->assertSame('platform', $row['managed_by']);
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

        $admin = $this->makeUser($tenant, 'admin');

        TenantContext::bypass(false);
        TenantContext::set($tenant);
        config(['saas.default_tenant_slug' => $tenant->slug]);

        return [$tenant, $admin];
    }

    private function makeUser(Tenant $tenant, string $roleName): User
    {
        $wasBypassed = TenantContext::isBypassed();
        TenantContext::bypass(true);

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $roleName,
            'display_name' => ucfirst($roleName),
            'permissions' => RoleAccess::permissionKeys(),
            'visibility_areas' => RoleAccess::defaultVisibilityAreas($roleName === 'admin' ? 'admin' : 'manager'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes($roleName === 'admin' ? 'admin' : 'manager'),
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => ucfirst($roleName),
            'email' => $roleName.'-'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass($wasBypassed);

        return $user;
    }
}
