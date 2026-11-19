<?php

namespace Tests\Feature\Saas;

use App\Models\Contractor;
use App\Models\MailBlockedSender;
use App\Models\McpDataLink;
use App\Models\OrderNumberingRule;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\AiUsageAnalyticsService;
use App\Services\CompletedOrderFinancialAnalytics;
use App\Services\Saas\TenantErasureService;
use App\Services\Saas\TenantExportService;
use App\Support\PlatformHost;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\SaasTestCase;
use ZipArchive;

class TenantLeakClosureTest extends SaasTestCase
{
    public function test_closed_order_revenue_stays_inside_tenant(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants('rev');

        TenantContext::set($tenantA);
        $this->insertOrderRow([
            'tenant_id' => $tenantA->id,
            'order_number' => 'REV-A',
            'company_code' => 'ORD',
            'order_date' => now()->toDateString(),
            'status' => 'closed',
            'customer_rate' => 5000,
            'delta' => 100,
            'is_active' => true,
        ]);

        TenantContext::set($tenantB);
        $this->insertOrderRow([
            'tenant_id' => $tenantB->id,
            'order_number' => 'REV-B',
            'company_code' => 'ORD',
            'order_date' => now()->toDateString(),
            'status' => 'closed',
            'customer_rate' => 9000,
            'delta' => 100,
            'is_active' => true,
        ]);

        TenantContext::set($tenantA);
        $buckets = app(CompletedOrderFinancialAnalytics::class)->monthlyBucketsAggregate(
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        );
        $income = array_sum(array_column($buckets, 'income'));

        $this->assertSame(5000.0, $income);
    }

    public function test_crm_user_on_platform_host_does_not_open_orders(): void
    {
        config(['app.platform_domain' => 'platform.test']);

        TenantContext::bypass(true);
        $tenant = Tenant::query()->create([
            'slug' => 'plat-crm-'.uniqid(),
            'name' => 'Plat CRM',
            'status' => 'active',
            'plan' => 'pro',
            'settings' => ['onboarding' => ['completed_at' => now()->toIso8601String()]],
        ]);
        $user = $this->admin($tenant);
        TenantContext::bypass(false);

        $this->actingAs($user)
            ->get(PlatformHost::url('/orders'))
            ->assertNotFound();

        $this->assertFalse(TenantContext::isBypassed());
    }

    public function test_numbering_blocklist_and_mcp_links_stay_inside_tenant(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants('edge');

        TenantContext::set($tenantA);
        $contractor = Contractor::query()->create([
            'tenant_id' => $tenantA->id,
            'type' => 'customer',
            'name' => 'Number Co',
        ]);
        $rule = OrderNumberingRule::query()->create([
            'cipher' => 'A'.substr(uniqid(), -6),
            'own_company_id' => $contractor->id,
        ]);
        MailBlockedSender::query()->create([
            'email' => 'blocked-'.uniqid().'@example.com',
        ]);
        $blocked = MailBlockedSender::query()->value('email');
        McpDataLink::query()->create([
            'source_key' => 'orders',
            'target_key' => 'leads-'.uniqid(),
        ]);
        $linkId = McpDataLink::query()->value('id');

        TenantContext::set($tenantB);
        $this->assertNull(OrderNumberingRule::query()->find($rule->id));
        $this->assertNotContains($blocked, MailBlockedSender::query()->pluck('email')->all());
        McpDataLink::query()->delete();

        TenantContext::set($tenantA);
        $this->assertNotNull(McpDataLink::query()->find($linkId));
    }

    public function test_start_plan_forbids_ungated_modules(): void
    {
        TenantContext::bypass(true);
        $tenant = Tenant::query()->create([
            'slug' => 'start-gate-'.uniqid(),
            'name' => 'Start Gate',
            'status' => 'active',
            'plan' => 'start',
            'settings' => ['onboarding' => ['completed_at' => now()->toIso8601String()]],
        ]);
        $user = $this->admin($tenant);
        TenantContext::bypass(false);
        config(['saas.default_tenant_slug' => $tenant->slug]);

        $this->actingAs($user);
        foreach ([
            '/budgeting',
            '/disposition',
            '/company-planning',
            '/modules/how-much-fits',
            '/mobile/shell/orders',
            '/settings/ai-analytics',
        ] as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    public function test_ai_usage_insights_hide_other_tenant_prompts(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants('ai');
        TenantContext::bypass(true);
        $userA = $this->admin($tenantA);
        TenantContext::bypass(false);

        TenantContext::set($tenantA);
        DB::table('ai_interaction_events')->insert([
            'tenant_id' => $tenantA->id,
            'user_id' => $userA->id,
            'feature' => 'command_bar',
            'event_type' => 'conversation_turn',
            'ok' => true,
            'user_prompt_redacted' => 'secret-prompt-from-a',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::set($tenantB);
        $encoded = json_encode(app(AiUsageAnalyticsService::class)->insights());

        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('secret-prompt-from-a', $encoded);
    }

    public function test_export_includes_people_and_erase_removes_only_that_tenant(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants('pd');

        TenantContext::set($tenantA);
        Contractor::query()->create([
            'tenant_id' => $tenantA->id,
            'type' => 'customer',
            'name' => 'Erase Me',
        ]);

        TenantContext::set($tenantB);
        $kept = Contractor::query()->create([
            'tenant_id' => $tenantB->id,
            'type' => 'customer',
            'name' => 'Keep Me',
        ]);

        $path = app(TenantExportService::class)->exportToZip($tenantA);
        $zip = new ZipArchive;
        $zip->open($path);
        $contractors = $zip->getFromName('contractors.json');
        $zip->close();
        @unlink($path);

        $this->assertIsString($contractors);
        $this->assertStringContainsString('Erase Me', $contractors);

        app(TenantErasureService::class)->erase($tenantA);

        TenantContext::bypass(true);
        $this->assertNull(Tenant::query()->find($tenantA->id));
        $this->assertNotNull(Contractor::query()->withoutGlobalScopes()->find($kept->id));
        TenantContext::bypass(false);
    }

    /**
     * @return array{0: Tenant, 1: Tenant}
     */
    private function twoTenants(string $prefix): array
    {
        TenantContext::bypass(true);

        $tenantA = Tenant::query()->create([
            'slug' => $prefix.'-a-'.uniqid(),
            'name' => $prefix.' A',
            'status' => 'active',
            'plan' => 'pro',
        ]);
        $tenantB = Tenant::query()->create([
            'slug' => $prefix.'-b-'.uniqid(),
            'name' => $prefix.' B',
            'status' => 'active',
            'plan' => 'pro',
        ]);

        TenantContext::bypass(false);

        return [$tenantA, $tenantB];
    }

    private function admin(Tenant $tenant): User
    {
        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'admin',
            'display_name' => 'Admin',
            'permissions' => RoleAccess::permissionKeys(),
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('admin'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('admin'),
        ]);

        return User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }
}
