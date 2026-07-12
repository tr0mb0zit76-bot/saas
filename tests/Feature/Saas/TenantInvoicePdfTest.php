<?php

namespace Tests\Feature\Saas;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantInvoice;
use App\Models\User;
use App\Services\Saas\TenantBillingService;
use App\Services\Saas\TenantProvisioner;
use App\Support\PlatformHost;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\SaasTestCase;

class TenantInvoicePdfTest extends SaasTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.platform_domain' => 'platform.test',
            'saas.platform_admin_emails' => ['platform-admin@saas.local'],
            'document_preview.driver' => 'gotenberg',
            'document_preview.gotenberg.url' => 'http://gotenberg.test',
        ]);
    }

    public function test_platform_admin_can_download_invoice_pdf(): void
    {
        Http::fake([
            'http://gotenberg.test/forms/chromium/convert/html' => Http::response('%PDF-1.4 mock', 200),
        ]);

        TenantContext::bypass(true);

        $hostTenant = Tenant::query()->create([
            'slug' => 'pdf-host-'.uniqid(),
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

        $tenant = Tenant::query()->create([
            'slug' => 'pdf-tenant-'.uniqid(),
            'name' => 'PDF Tenant',
            'status' => 'trial',
            'plan' => 'start',
        ]);

        app(TenantProvisioner::class)->provision($tenant);
        app(TenantBillingService::class)->markInvoicePaid($tenant->fresh(), 9900.00);

        $invoice = TenantInvoice::query()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($invoice);

        TenantContext::bypass(false);
        config(['saas.default_tenant_slug' => $hostTenant->slug]);

        $response = $this->actingAs($admin)->get(
            PlatformHost::url("/tenants/{$tenant->id}/invoices/{$invoice->id}/pdf"),
        );

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
