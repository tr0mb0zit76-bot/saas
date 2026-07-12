<?php

namespace Tests\Feature\Saas;

use App\Mail\TenantWelcomeMail;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PlatformHost;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\SaasTestCase;

class TenantOnboardingTest extends SaasTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.platform_domain' => 'platform.test',
            'saas.platform_admin_emails' => ['platform-admin@saas.local'],
        ]);
    }

    public function test_platform_create_tenant_provisions_admin_user_and_sends_invite(): void
    {
        Mail::fake();

        TenantContext::bypass(true);

        $hostTenant = Tenant::query()->create([
            'slug' => 'plat-onboard-host-'.uniqid(),
            'name' => 'Host',
            'status' => 'active',
            'plan' => 'pro',
        ]);

        $role = Role::query()->create([
            'tenant_id' => $hostTenant->id,
            'name' => 'admin',
            'display_name' => 'Admin',
            'permissions' => RoleAccess::permissionKeys(),
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('admin'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('admin'),
        ]);

        $admin = User::query()->create([
            'tenant_id' => $hostTenant->id,
            'role_id' => $role->id,
            'name' => 'Platform Admin',
            'email' => 'platform-admin@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(false);
        config(['saas.default_tenant_slug' => $hostTenant->slug]);

        $slug = 'pilot-'.uniqid();
        $adminEmail = 'owner-'.uniqid().'@example.com';

        $this->actingAs($admin)->post(PlatformHost::url('/tenants'), [
            'slug' => $slug,
            'name' => 'Pilot Co',
            'status' => 'trial',
            'plan' => 'start',
            'admin_name' => 'Иван Пилот',
            'admin_email' => $adminEmail,
            'send_invite' => true,
        ])->assertRedirect(route('platform.tenants.index'));

        TenantContext::bypass(true);

        $tenant = Tenant::query()->where('slug', $slug)->first();
        $this->assertNotNull($tenant);
        $this->assertSame(7, Role::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        $owner = User::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $adminEmail)
            ->first();

        $this->assertNotNull($owner);
        $this->assertSame('Иван Пилот', $owner->name);
        $this->assertSame('admin', Role::query()->withoutGlobalScopes()->find($owner->role_id)?->name);

        TenantContext::bypass(false);

        Mail::assertSent(TenantWelcomeMail::class, fn (TenantWelcomeMail $mail): bool => $mail->hasTo($adminEmail));
    }
}
