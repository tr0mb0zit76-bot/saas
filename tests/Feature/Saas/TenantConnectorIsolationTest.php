<?php

namespace Tests\Feature\Saas;

use App\Jobs\Integrations\RunTenantConnectorJob;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Saas\TenantConnectorService;
use App\Support\PlatformHost;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\SaasTestCase;

class TenantConnectorIsolationTest extends SaasTestCase
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

        $host = Tenant::query()->create([
            'slug' => 'conn-host-'.uniqid(),
            'name' => 'Conn Host',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $role = Role::query()->create([
            'tenant_id' => $host->id,
            'name' => 'admin',
            'display_name' => 'Admin',
            'permissions' => RoleAccess::permissionKeys(),
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('admin'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('admin'),
        ]);

        $this->platformAdmin = User::query()->create([
            'tenant_id' => $host->id,
            'role_id' => $role->id,
            'name' => 'Platform Admin',
            'email' => 'platform-admin@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(false);
        config(['saas.default_tenant_slug' => $host->slug]);
    }

    public function test_secrets_encrypted_per_tenant_and_not_in_platform_props(): void
    {
        TenantContext::bypass(true);

        $tenantA = Tenant::query()->create([
            'slug' => 'conn-a-'.uniqid(),
            'name' => 'Conn A',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        $tenantB = Tenant::query()->create([
            'slug' => 'conn-b-'.uniqid(),
            'name' => 'Conn B',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $service = app(TenantConnectorService::class);
        $boundA = $service->bind($tenantA, 'one_c_bp', ['base_url' => 'https://a.example'], [], [
            'username' => 'user-a',
            'password' => 'secret-a',
        ]);
        $service->bind($tenantB, 'one_c_bp', ['base_url' => 'https://b.example'], [], [
            'username' => 'user-b',
            'password' => 'secret-b',
        ]);

        $rawA = data_get($tenantA->fresh()->settings, 'integrations.0.secrets.password');
        $this->assertIsString($rawA);
        $this->assertStringStartsWith('enc:', $rawA);
        $this->assertStringNotContainsString('secret-a', $rawA);
        $this->assertSame('secret-a', $service->decryptedSecrets($tenantA->fresh(), $boundA['id'])['password']);

        $props = $service->listForPlatform($tenantA->fresh());
        $this->assertArrayNotHasKey('secrets', $props[0]);
        $this->assertTrue($props[0]['has_secrets']);
        $this->assertEqualsCanonicalizing(['username', 'password'], $props[0]['secret_keys']);

        $rawB = (string) data_get($tenantB->fresh()->settings, 'integrations.0.secrets.password');
        $this->assertNotSame($rawA, $rawB);

        TenantContext::bypass(false);
    }

    public function test_settings_cannot_elevate_without_integrations_feature(): void
    {
        TenantContext::bypass(true);
        $tenant = Tenant::query()->create([
            'slug' => 'conn-start-'.uniqid(),
            'name' => 'Start',
            'status' => 'active',
            'plan' => 'start',
        ]);
        TenantContext::bypass(false);

        $this->expectException(\RuntimeException::class);
        app(TenantConnectorService::class)->bind($tenant, 'one_c_bp', [], [], ['password' => 'x']);
    }

    public function test_unknown_adapter_rejected(): void
    {
        TenantContext::bypass(true);
        $tenant = Tenant::query()->create([
            'slug' => 'conn-bad-'.uniqid(),
            'name' => 'Bad',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        TenantContext::bypass(false);

        $this->expectException(\InvalidArgumentException::class);
        app(TenantConnectorService::class)->bind($tenant, 'not-a-real-adapter', []);
    }

    public function test_job_without_tenant_context_aborts(): void
    {
        TenantContext::clear();

        $this->expectException(\RuntimeException::class);

        // tenant_id set but unknown → SetTenantFromJob fails closed (no silent global run).
        RunTenantConnectorJob::dispatchSync(0, 'missing');
    }

    public function test_job_runs_only_with_feature_and_enabled_connector(): void
    {
        TenantContext::bypass(true);
        $tenant = Tenant::query()->create([
            'slug' => 'conn-job-'.uniqid(),
            'name' => 'Job',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        $bound = app(TenantConnectorService::class)->bind($tenant, 'one_c_bp', ['base_url' => 'https://x'], [], [], true);
        TenantContext::bypass(false);

        TenantContext::set($tenant);
        (new RunTenantConnectorJob($tenant->id, $bound['id']))->handle(app(TenantConnectorService::class));
        $this->assertTrue(true);

        $tenant->update(['plan' => 'start', 'settings' => array_merge(
            is_array($tenant->settings) ? $tenant->settings : [],
            [],
        )]);
        // Drop integrations by plan change — keep connector rows but feature off.
        $tenant->forceFill(['plan' => 'start'])->save();
        TenantContext::set($tenant->fresh());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('feature:integrations is off');
        (new RunTenantConnectorJob($tenant->id, $bound['id']))->handle(app(TenantConnectorService::class));
    }

    public function test_platform_ops_bind_connector_ui(): void
    {
        TenantContext::bypass(true);
        $tenant = Tenant::query()->create([
            'slug' => 'conn-ui-'.uniqid(),
            'name' => 'UI',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        TenantContext::bypass(false);

        $this->actingAs($this->platformAdmin)
            ->patch(PlatformHost::url('/tenants/'.$tenant->id.'/ops'), [
                'connector' => [
                    'adapter_key' => 'one_c_bp',
                    'enabled' => true,
                    'base_url' => 'https://1c.example/ib',
                    'username' => 'odata',
                    'password' => 'top-secret',
                ],
            ])
            ->assertRedirect();

        $tenant->refresh();
        $list = app(TenantConnectorService::class)->listForPlatform($tenant);
        $this->assertCount(1, $list);
        $this->assertSame('one_c_bp', $list[0]['adapter_key']);
        $this->assertTrue($list[0]['has_secrets']);

        $stored = (string) data_get($tenant->settings, 'integrations.0.secrets.password');
        $this->assertStringStartsWith('enc:', $stored);
        $this->assertSame('top-secret', Crypt::decryptString(substr($stored, 4)));
    }

    public function test_orientum_clone_template_writes_tenant_scoped_connector(): void
    {
        TenantContext::bypass(true);
        $orientum = Tenant::query()->create([
            'slug' => 'orientum-clone-'.uniqid(),
            'name' => 'Orientum Clone',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        TenantContext::bypass(false);

        putenv('ONE_C_USERNAME=aa-user');
        putenv('ONE_C_PASSWORD=aa-pass');
        $_ENV['ONE_C_USERNAME'] = 'aa-user';
        $_ENV['ONE_C_PASSWORD'] = 'aa-pass';

        $bound = app(TenantConnectorService::class)->cloneFromTemplate($orientum, 'aa_one_c_bp');
        $this->assertSame('one_c_bp', $bound['adapter_key']);
        $this->assertTrue($bound['has_secrets']);

        $secrets = app(TenantConnectorService::class)->decryptedSecrets($orientum->fresh(), $bound['id']);
        $this->assertSame('aa-user', $secrets['username']);
        $this->assertSame('aa-pass', $secrets['password']);

        // Class B remains empty unless provisioned.
        TenantContext::bypass(true);
        $external = Tenant::query()->create([
            'slug' => 'no-clone-'.uniqid(),
            'name' => 'External',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        TenantContext::bypass(false);
        $this->assertSame([], app(TenantConnectorService::class)->listForPlatform($external));
    }
}
