<?php

namespace Tests\Feature\Saas;

use App\Models\OrderDocument;
use App\Models\Role;
use App\Models\SalesBookArticle;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ContentPacks\SalesBookContentPackInstaller;
use App\Services\Saas\TenantProvisioner;
use App\Support\PlatformHost;
use App\Support\PrintFormVerificationCode;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Tests\SaasTestCase;

class ContentPacksAndPrintVerificationTest extends SaasTestCase
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
            'slug' => 'ops-host-'.uniqid(),
            'name' => 'Ops Host',
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

    public function test_class_b_provision_gets_user_guide_only_and_verification_off(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'ext-b-'.uniqid(),
            'name' => 'External B',
            'status' => 'active',
            'plan' => 'pro',
        ]);

        app(TenantProvisioner::class)->provision($tenant);
        $tenant->refresh();

        $this->assertFalse($tenant->settingBool('print.document_verification', true));
        $this->assertSame(
            [SalesBookContentPackInstaller::PACK_CRM_USER_GUIDE],
            data_get($tenant->settings, 'content_packs.sales_book'),
        );

        TenantContext::set($tenant);
        $titles = SalesBookArticle::query()->pluck('title')->all();
        $this->assertTrue(collect($titles)->contains(fn (string $t): bool => str_contains($t, 'пользователя')));
        $this->assertFalse(collect($titles)->contains(fn (string $t): bool => str_contains($t, 'Плейбук')));
        $this->assertFalse(collect($titles)->contains(fn (string $t): bool => str_contains($t, 'администратора')));

        TenantContext::bypass(false);
    }

    public function test_class_a_orientum_gets_full_packs_and_verification_on(): void
    {
        TenantContext::bypass(true);

        $orientum = Tenant::query()->create([
            'slug' => 'orientum-'.uniqid(),
            'name' => 'Orientum',
            'status' => 'active',
            'plan' => 'enterprise',
            'settings' => [],
        ]);

        $settings = [];
        data_set($settings, 'print.document_verification', true);
        $orientum->forceFill(['settings' => $settings])->save();

        app(SalesBookContentPackInstaller::class)->installMany(
            $orientum->fresh(),
            SalesBookContentPackInstaller::classAPacks(),
        );

        $orientum->refresh();
        TenantContext::bypass(false);

        $this->assertTrue($orientum->settingBool('print.document_verification', false));
        $this->assertEqualsCanonicalizing(
            SalesBookContentPackInstaller::classAPacks(),
            data_get($orientum->settings, 'content_packs.sales_book'),
        );

        TenantContext::set($orientum);
        $this->assertGreaterThanOrEqual(9, SalesBookArticle::query()->count());
    }

    public function test_print_verification_setting_is_not_a_plan_feature(): void
    {
        TenantContext::bypass(true);
        $tenant = Tenant::query()->create([
            'slug' => 'feat-'.uniqid(),
            'name' => 'Feat',
            'status' => 'active',
            'plan' => 'pro',
            'settings' => ['print' => ['document_verification' => true]],
        ]);
        TenantContext::bypass(false);

        $this->assertTrue($tenant->settingBool('print.document_verification', false));
        $this->assertFalse($tenant->featureEnabled('document_verification'));
        $this->assertNotContains('document_verification', $tenant->enabledFeatures());
    }

    public function test_public_verify_404_when_setting_off_and_ok_when_on(): void
    {
        TenantContext::bypass(true);
        $tenant = Tenant::query()->create([
            'slug' => 'verify-'.uniqid(),
            'name' => 'Verify',
            'status' => 'active',
            'plan' => 'pro',
            'settings' => ['print' => ['document_verification' => false]],
        ]);
        TenantContext::bypass(false);
        TenantContext::set($tenant);
        config(['saas.default_tenant_slug' => $tenant->slug]);

        $document = OrderDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'metadata' => ['pdf_certified_sha256' => str_repeat('b', 64)],
        ]);

        $code = PrintFormVerificationCode::forOrderDocument($document);

        $this->get(route('print-verification.order-documents.show', [
            'orderDocument' => $document,
            'code' => $code,
        ]))->assertNotFound();

        $tenant->update([
            'settings' => ['print' => ['document_verification' => true]],
        ]);

        $this->get(route('print-verification.order-documents.show', [
            'orderDocument' => $document,
            'code' => $code,
        ]))->assertOk();
    }

    public function test_pack_install_is_tenant_scoped_a_vs_b(): void
    {
        TenantContext::bypass(true);

        $tenantA = Tenant::query()->create([
            'slug' => 'pack-a-'.uniqid(),
            'name' => 'Pack A',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        $tenantB = Tenant::query()->create([
            'slug' => 'pack-b-'.uniqid(),
            'name' => 'Pack B',
            'status' => 'active',
            'plan' => 'pro',
        ]);

        $installer = app(SalesBookContentPackInstaller::class);
        $installer->installMany($tenantA, SalesBookContentPackInstaller::classAPacks());
        $installer->installMany($tenantB, SalesBookContentPackInstaller::classBDefaultPacks());

        TenantContext::bypass(false);
        TenantContext::set($tenantA);
        $countA = SalesBookArticle::query()->count();

        TenantContext::set($tenantB);
        $countB = SalesBookArticle::query()->count();

        $this->assertSame(9, $countA);
        $this->assertSame(3, $countB);
        $this->assertGreaterThan($countB, $countA);

        TenantContext::bypass(true);
        $this->assertSame(
            $countA,
            SalesBookArticle::query()->withoutGlobalScopes()->where('tenant_id', $tenantA->id)->count(),
        );
        $this->assertSame(
            $countB,
            SalesBookArticle::query()->withoutGlobalScopes()->where('tenant_id', $tenantB->id)->count(),
        );
        TenantContext::bypass(false);
    }

    public function test_platform_ops_can_toggle_verification_and_install_pack(): void
    {
        TenantContext::bypass(true);
        $tenant = Tenant::query()->create([
            'slug' => 'ops-t-'.uniqid(),
            'name' => 'Ops Target',
            'status' => 'active',
            'plan' => 'pro',
            'settings' => ['print' => ['document_verification' => false]],
        ]);
        TenantContext::bypass(false);

        $this->actingAs($this->platformAdmin)
            ->get(PlatformHost::url('/tenants/'.$tenant->id.'/ops'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Platform/Tenants/Ops')
                ->where('document_verification', false));

        $this->actingAs($this->platformAdmin)
            ->patch(PlatformHost::url('/tenants/'.$tenant->id.'/ops'), [
                'document_verification' => true,
            ])
            ->assertRedirect();

        $this->assertTrue($tenant->fresh()->settingBool('print.document_verification', false));

        $this->actingAs($this->platformAdmin)
            ->patch(PlatformHost::url('/tenants/'.$tenant->id.'/ops'), [
                'install_pack' => SalesBookContentPackInstaller::PACK_ADMIN_GUIDE,
            ])
            ->assertRedirect();

        $this->assertContains(
            SalesBookContentPackInstaller::PACK_ADMIN_GUIDE,
            data_get($tenant->fresh()->settings, 'content_packs.sales_book', []),
        );
    }

    private function platformUrl(string $path = '/'): string
    {
        return PlatformHost::url($path);
    }
}
