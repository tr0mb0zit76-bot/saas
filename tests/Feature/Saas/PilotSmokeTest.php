<?php

namespace Tests\Feature\Saas;

use App\Mail\TenantWelcomeMail;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Saas\TenantBillingService;
use App\Services\Saas\TenantUsageLimiter;
use App\Support\PlatformHost;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\SaasTestCase;

/**
 * Automated pilot smoke — mirrors docs/sync/pilot-smoke-checklist.md.
 * Run: php artisan test tests/Feature/Saas/PilotSmokeTest.php
 */
class PilotSmokeTest extends SaasTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.platform_domain' => 'platform.test',
            'saas.platform_admin_emails' => ['pilot-platform@saas.local'],
            'showcase.mode' => 'traklo_pro',
            'document_preview.driver' => 'gotenberg',
            'document_preview.gotenberg.url' => 'http://gotenberg.test',
        ]);
    }

    public function test_pilot_smoke_end_to_end(): void
    {
        Mail::fake();
        Http::fake([
            'http://gotenberg.test/forms/chromium/convert/html' => Http::response('%PDF-1.4 pilot', 200),
        ]);

        // 1. Витрина Traklo Pro
        $this->get($this->showcaseUrl('/'))->assertOk();

        // 2. Login page
        TenantContext::bypass(true);
        $hostTenant = $this->seedPlatformHostTenant();
        TenantContext::bypass(false);
        config(['saas.default_tenant_slug' => $hostTenant->slug]);

        $this->get($this->crmUrl('/login'))->assertOk();

        // 3. Platform: create pilot tenant with onboarding
        $platformAdmin = User::query()->withoutGlobalScopes()
            ->where('email', 'pilot-platform@saas.local')
            ->first();

        $slug = 'pilot-co-'.uniqid();
        $ownerEmail = 'owner-'.uniqid().'@pilot.example.com';

        $this->actingAs($platformAdmin)->post(PlatformHost::url('/tenants'), [
            'slug' => $slug,
            'name' => 'Pilot Forwarding LLC',
            'status' => 'trial',
            'plan' => 'start',
            'admin_name' => 'Алексей Пилот',
            'admin_email' => $ownerEmail,
            'send_invite' => true,
        ])->assertRedirect(route('platform.tenants.index'));

        Mail::assertSent(TenantWelcomeMail::class, fn (TenantWelcomeMail $mail): bool => $mail->hasTo($ownerEmail));

        TenantContext::bypass(true);

        $pilotTenant = Tenant::query()->where('slug', $slug)->first();
        $this->assertNotNull($pilotTenant);
        $this->assertSame(7, Role::query()->withoutGlobalScopes()->where('tenant_id', $pilotTenant->id)->count());

        $owner = User::query()->withoutGlobalScopes()
            ->where('tenant_id', $pilotTenant->id)
            ->where('email', $ownerEmail)
            ->first();

        $this->assertNotNull($owner);

        // 4. Tenant owner CRM access + Start plan gating
        TenantContext::bypass(false);
        config(['saas.default_tenant_slug' => $slug]);
        TenantContext::set($pilotTenant);

        $this->actingAs($owner->fresh())->get('/leads')->assertOk();
        $this->actingAs($owner->fresh())->get('/contractors')->assertOk();
        $this->actingAs($owner->fresh())->get('/mail')->assertForbidden();

        // 5. Usage limits (5 users on Start)
        $adminRole = Role::query()->withoutGlobalScopes()
            ->where('tenant_id', $pilotTenant->id)
            ->where('name', 'admin')
            ->first();

        while (User::query()->withoutGlobalScopes()->where('tenant_id', $pilotTenant->id)->count() < 5) {
            User::query()->create([
                'tenant_id' => $pilotTenant->id,
                'role_id' => $adminRole->id,
                'name' => 'Staff '.uniqid(),
                'email' => 'staff-'.uniqid().'@pilot.example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
        }

        try {
            app(TenantUsageLimiter::class)->assertCanAddUser($pilotTenant);
            $this->fail('Expected user limit ValidationException');
        } catch (ValidationException) {
            // expected
        }

        // 6. Billing: mark paid + PDF
        TenantContext::bypass(true);
        app(TenantBillingService::class)->markInvoicePaid($pilotTenant->fresh(), 15000.00);
        $this->assertSame('active', $pilotTenant->fresh()->status);

        $invoice = $pilotTenant->fresh()->invoices()->latest('id')->first();
        $this->assertNotNull($invoice);

        TenantContext::bypass(false);
        config(['saas.default_tenant_slug' => $hostTenant->slug]);

        $pdfResponse = $this->actingAs($platformAdmin)->get(
            PlatformHost::url("/tenants/{$pilotTenant->id}/invoices/{$invoice->id}/pdf"),
        );

        $pdfResponse->assertOk();
        $this->assertStringStartsWith('%PDF', $pdfResponse->getContent());

        // 7. Feature override grants mail on Start
        TenantContext::bypass(true);
        $settings = is_array($pilotTenant->settings) ? $pilotTenant->settings : [];
        $settings['features'] = ['mail' => true];
        $pilotTenant->update(['settings' => $settings]);
        TenantContext::bypass(false);
        TenantContext::set($pilotTenant->fresh());

        $this->actingAs($owner->fresh())->get('/mail')->assertOk();
    }

    private function seedPlatformHostTenant(): Tenant
    {
        $tenant = Tenant::query()->create([
            'slug' => 'pilot-host-'.uniqid(),
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

        User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => 'Platform Admin',
            'email' => 'pilot-platform@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        return $tenant;
    }

    private function showcaseUrl(string $path = '/'): string
    {
        $host = config('app.showcase_hosts')[0] ?? 'v5.local';

        return 'http://'.$host.$path;
    }

    private function crmUrl(string $path = '/'): string
    {
        $host = config('app.crm_domain') ?: 'localhost';

        return 'http://'.$host.$path;
    }
}
