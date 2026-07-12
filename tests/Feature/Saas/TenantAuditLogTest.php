<?php

namespace Tests\Feature\Saas;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantAuditLog;
use App\Models\User;
use App\Services\Saas\TenantAuditLogger;
use App\Support\PlatformHost;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Tests\SaasTestCase;

class TenantAuditLogTest extends SaasTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.platform_domain' => 'platform.test',
            'saas.platform_admin_emails' => ['platform-admin@saas.local'],
        ]);
    }

    public function test_platform_tenant_update_writes_audit_log(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'audit-log-'.uniqid(),
            'name' => 'Before Name',
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

        $this->actingAs($admin)->patch($this->platformUrl("/tenants/{$tenant->id}"), [
            'name' => 'After Name',
            'status' => 'active',
            'plan' => 'pro',
            'trial_ends_at' => null,
        ])->assertRedirect(route('platform.tenants.index'));

        $log = TenantAuditLog::query()
            ->where('tenant_id', $tenant->id)
            ->where('action', 'tenant.updated')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Before Name', $log->old_values['name'] ?? null);
        $this->assertSame('After Name', $log->new_values['name'] ?? null);
        $this->assertSame('pro', $log->new_values['plan'] ?? null);
    }

    public function test_audit_logger_redacts_sensitive_values(): void
    {
        app(TenantAuditLogger::class)->log(
            null,
            null,
            'test.redact',
            null,
            null,
            ['password' => 'secret-old'],
            ['token' => 'secret-new', 'name' => 'ok'],
        );

        $log = TenantAuditLog::query()->where('action', 'test.redact')->first();

        $this->assertNotNull($log);
        $this->assertSame('[redacted]', $log->old_values['password'] ?? null);
        $this->assertSame('[redacted]', $log->new_values['token'] ?? null);
        $this->assertSame('ok', $log->new_values['name'] ?? null);
    }

    public function test_platform_admin_can_view_audit_log_page(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'audit-view-'.uniqid(),
            'name' => 'Audit View',
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

        $admin = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => 'Platform Admin',
            'email' => 'platform-admin@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantAuditLog::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'action' => 'tenant.created',
            'entity_type' => 'tenant',
            'entity_id' => $tenant->id,
            'new_values' => ['slug' => $tenant->slug],
        ]);

        TenantContext::bypass(false);

        $this->actingAs($admin)
            ->get($this->platformUrl('/audit'))
            ->assertOk()
            ->assertSee('tenant.created');
    }

    private function platformUrl(string $path = '/'): string
    {
        return PlatformHost::url($path);
    }
}
