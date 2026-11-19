<?php

namespace Tests\Feature\Saas;

use App\Mail\TenantWelcomeMail;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Saas\TenantProvisioner;
use App\Support\PlatformHost;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\SaasTestCase;

class PlatformTenantResendInviteTest extends SaasTestCase
{
    private User $platformAdmin;

    private Tenant $targetTenant;

    private User $tenantAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.platform_domain' => 'platform.test',
            'saas.platform_admin_emails' => ['platform-admin@saas.local'],
        ]);

        TenantContext::bypass(true);

        $host = Tenant::query()->create([
            'slug' => 'resend-host-'.uniqid(),
            'name' => 'Resend Host',
            'status' => 'active',
            'plan' => 'pro',
        ]);

        $hostRole = Role::query()->create([
            'tenant_id' => $host->id,
            'name' => 'admin',
            'display_name' => 'Admin',
            'permissions' => RoleAccess::permissionKeys(),
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('admin'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('admin'),
        ]);

        $this->platformAdmin = User::query()->create([
            'tenant_id' => $host->id,
            'role_id' => $hostRole->id,
            'name' => 'Platform Admin',
            'email' => 'platform-admin@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->targetTenant = Tenant::query()->create([
            'slug' => 'resend-target-'.uniqid(),
            'name' => 'Resend Target',
            'status' => 'trial',
            'plan' => 'start',
        ]);

        app(TenantProvisioner::class)->provision($this->targetTenant);

        $adminRoleId = Role::query()
            ->where('tenant_id', $this->targetTenant->id)
            ->where('name', 'admin')
            ->value('id');

        $this->tenantAdmin = User::query()->create([
            'tenant_id' => $this->targetTenant->id,
            'role_id' => $adminRoleId,
            'name' => 'Tenant Owner',
            'email' => 'owner-'.uniqid().'@example.com',
            'password' => Hash::make('old-password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(false);
        config(['saas.default_tenant_slug' => $host->slug]);
    }

    public function test_platform_admin_can_resend_invite_and_mail_is_sent(): void
    {
        Mail::fake();

        $oldHash = $this->tenantAdmin->password;

        $this->actingAs($this->platformAdmin)
            ->get(PlatformHost::url('/tenants/'.$this->targetTenant->id.'/ops'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Platform/Tenants/Ops')
                ->where('primary_admin.email', $this->tenantAdmin->email));

        $this->actingAs($this->platformAdmin)
            ->post(PlatformHost::url('/tenants/'.$this->targetTenant->id.'/ops/resend-invite'))
            ->assertRedirect(route('platform.tenants.ops', $this->targetTenant))
            ->assertSessionHas('flash.type', 'success');

        $this->tenantAdmin->refresh();
        $this->assertNotSame($oldHash, $this->tenantAdmin->password);

        Mail::assertSent(
            TenantWelcomeMail::class,
            fn (TenantWelcomeMail $mail): bool => $mail->hasTo($this->tenantAdmin->email),
        );
    }

    public function test_non_platform_admin_cannot_resend_invite(): void
    {
        Mail::fake();

        TenantContext::bypass(true);

        $regular = User::query()->create([
            'tenant_id' => $this->targetTenant->id,
            'role_id' => $this->tenantAdmin->role_id,
            'name' => 'Regular Admin',
            'email' => 'regular-'.uniqid().'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(false);

        $this->actingAs($regular)
            ->post(PlatformHost::url('/tenants/'.$this->targetTenant->id.'/ops/resend-invite'))
            ->assertRedirect(PlatformHost::url('/login'))
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
        $this->assertGuest();
    }
}
