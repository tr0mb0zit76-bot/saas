<?php

namespace Tests\Feature\Saas;

use App\Models\Contractor;
use App\Models\ContractorContact;
use App\Models\Lead;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Agents\AgentToolRegistry;
use App\Services\CarrierMatching\CarrierMatchingService;
use App\Services\ExternalUsers\ExternalUserProvisionService;
use App\Services\Mcp\AiToolAuditLogger;
use App\Support\AiInteractionFeature;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\SaasTestCase;

/**
 * Remaining saas-security audit items after P0 (a52bda4):
 * H3 audit redact, H4 write confirm, H1 contractor rebind, M1 slug spoof, M2 tenant exists, M5 quote lock.
 */
class SecurityP1P2IsolationTest extends SaasTestCase
{
    public function test_ai_tool_audit_logger_redacts_nested_pii_fields(): void
    {
        if (! Schema::hasTable('ai_tool_audit_logs')) {
            $this->markTestSkipped('ai_tool_audit_logs missing');
        }

        $fixture = $this->seedTenantFixture('pro');
        TenantContext::set($fixture['tenant']);

        app(AiToolAuditLogger::class)->log(
            $fixture['staff'],
            'create_fleet_driver',
            [
                'full_name' => 'Иван Иванов',
                'passport_series' => '4510',
                'passport_number' => '123456',
                'phone' => '+79001112233',
                'email' => 'driver@example.com',
                'nested' => [
                    'inn' => '7707083893',
                    'note' => 'связь driver@example.com',
                ],
            ],
            true,
            null,
            AiInteractionFeature::CommandBar,
        );

        $row = DB::table('ai_tool_audit_logs')->orderByDesc('id')->first();
        $this->assertNotNull($row);

        $args = json_decode((string) $row->arguments, true);
        $this->assertIsArray($args);
        $this->assertSame('[redacted]', $args['passport_series'] ?? null);
        $this->assertSame('[redacted]', $args['passport_number'] ?? null);
        $this->assertSame('[redacted]', $args['phone'] ?? null);
        $this->assertSame('[redacted]', $args['email'] ?? null);
        $this->assertSame('[redacted]', $args['nested']['inn'] ?? null);
        $this->assertStringContainsString('[redacted]', (string) ($args['nested']['note'] ?? ''));
    }

    public function test_high_risk_command_bar_write_requires_confirm_token(): void
    {
        $fixture = $this->seedTenantFixture('pro');
        TenantContext::set($fixture['tenant']);
        config(['ai.command_bar.require_write_confirmation' => true]);

        $registry = app(AgentToolRegistry::class);

        $dry = $registry->invoke($fixture['staff'], 'create_contractor', [
            'type' => 'customer',
            'name' => 'ООО Аудит',
        ]);

        $this->assertTrue($dry['dry_run'] ?? false);
        $this->assertNotEmpty($dry['confirm_token'] ?? null);
        $this->assertSame(0, Contractor::query()->where('name', 'ООО Аудит')->count());

        $written = $registry->invoke($fixture['staff'], 'create_contractor', [
            'type' => 'customer',
            'name' => 'ООО Аудит',
            'confirm_token' => $dry['confirm_token'],
        ]);

        $this->assertArrayNotHasKey('dry_run', $written);
        $this->assertArrayNotHasKey('error', $written);
        $this->assertSame(1, Contractor::query()->where('name', 'ООО Аудит')->count());
    }

    public function test_external_provision_rejects_contractor_rebind(): void
    {
        $fixture = $this->seedTenantFixture('pro');
        TenantContext::set($fixture['tenant']);

        $otherCarrier = Contractor::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'type' => 'carrier',
            'name' => 'Other Carrier',
            'is_active' => true,
        ]);

        $contact = ContractorContact::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'contractor_id' => $otherCarrier->id,
            'full_name' => 'Other Contact',
            'email' => $fixture['carrier']->email,
            'is_primary' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(ExternalUserProvisionService::class)->provisionInvite(
            $otherCarrier,
            $contact,
            $fixture['staff'],
        );
    }

    public function test_authenticated_x_tenant_slug_spoof_is_forbidden(): void
    {
        $tenantA = $this->seedTenantFixture('pro', 'a');
        $tenantB = $this->seedTenantFixture('pro', 'b');

        config(['saas.default_tenant_slug' => $tenantA['tenant']->slug]);
        TenantContext::set($tenantA['tenant']);
        $this->actingAs($tenantA['staff']);

        $this->withHeader('X-Tenant-Slug', $tenantB['tenant']->slug)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_share_rejects_cross_tenant_carrier_id_at_validation(): void
    {
        $tenantA = $this->seedTenantFixture('pro', 'a');
        $tenantB = $this->seedTenantFixture('pro', 'b');

        TenantContext::set($tenantA['tenant']);
        config(['saas.default_tenant_slug' => $tenantA['tenant']->slug]);

        $lead = Lead::query()->create([
            'tenant_id' => $tenantA['tenant']->id,
            'number' => 'SEC-M2-'.uniqid(),
            'title' => 'Share isolation',
            'status' => 'new',
            'source' => CarrierMatchingService::SOURCE_CUSTOMER_REQUEST,
            'counterparty_id' => $tenantA['customerContractor']->id,
            'responsible_id' => $tenantA['staff']->id,
            'created_by' => $tenantA['staff']->id,
            'updated_by' => $tenantA['staff']->id,
        ]);

        $this->actingAs($tenantA['staff']);

        $this->postJson(route('leads.carrier-matching.share', $lead), [
            'carrier_ids' => [$tenantB['carrierContractor']->id],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['carrier_ids.0']);
    }

    public function test_customer_cannot_edit_cargo_request_after_quote_sent(): void
    {
        $fixture = $this->seedTenantFixture('pro');
        TenantContext::set($fixture['tenant']);
        config(['saas.default_tenant_slug' => $fixture['tenant']->slug]);

        $lead = Lead::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'number' => 'SEC-M5-'.uniqid(),
            'title' => 'Locked after send',
            'status' => 'new',
            'source' => CarrierMatchingService::SOURCE_CUSTOMER_REQUEST,
            'counterparty_id' => $fixture['customerContractor']->id,
            'responsible_id' => $fixture['staff']->id,
            'created_by' => $fixture['customer']->id,
            'updated_by' => $fixture['customer']->id,
            'loading_location' => 'Москва',
            'unloading_location' => 'Казань',
            'quoted_price' => 100000,
            'quoted_currency' => 'RUB',
            'quote_status' => 'sent',
            'quote_sent_at' => now(),
        ]);

        $this->actingAs($fixture['customer']);

        $this->putJson(route('mobile.shell.counterparty.cargo-requests.update', $lead), [
            'title' => 'Hacked title',
            'loading_location' => 'Спб',
            'unloading_location' => 'Казань',
        ])->assertStatus(422);
    }

    /**
     * @return array{
     *     tenant: Tenant,
     *     staff: User,
     *     customer: User,
     *     carrier: User,
     *     customerContractor: Contractor,
     *     carrierContractor: Contractor
     * }
     */
    private function seedTenantFixture(string $plan, string $suffix = ''): array
    {
        TenantContext::bypass(true);

        $slug = 'sec-p12-'.$plan.($suffix !== '' ? '-'.$suffix : '').'-'.uniqid();

        $tenant = Tenant::query()->create([
            'slug' => $slug,
            'name' => 'Security P1/P2 '.$slug,
            'status' => 'active',
            'plan' => $plan,
            'settings' => [
                'onboarding' => ['completed_at' => now()->toIso8601String()],
                'features' => [
                    'carrier_matching' => true,
                    'traklo_mobile' => true,
                    'leads' => true,
                    'contractors' => true,
                    'orders' => true,
                ],
            ],
        ]);

        $adminRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'admin',
            'display_name' => 'Admin',
            'permissions' => RoleAccess::permissionKeys(),
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('admin'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('admin'),
        ]);

        $customerRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'counterparty_customer',
            'display_name' => 'Customer',
            'permissions' => [],
            'visibility_areas' => ['counterparty_orders', 'counterparty_documents', 'counterparty_portal'],
            'visibility_scopes' => [],
        ]);

        $carrierRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'counterparty_carrier',
            'display_name' => 'Carrier',
            'permissions' => [],
            'visibility_areas' => ['counterparty_orders', 'counterparty_documents', 'counterparty_portal'],
            'visibility_scopes' => [],
        ]);

        TenantContext::bypass(false);
        TenantContext::set($tenant);

        $customerContractor = Contractor::query()->create([
            'tenant_id' => $tenant->id,
            'type' => 'customer',
            'name' => 'Customer Co',
            'is_active' => true,
        ]);
        $carrierContractor = Contractor::query()->create([
            'tenant_id' => $tenant->id,
            'type' => 'carrier',
            'name' => 'Carrier Co',
            'is_active' => true,
        ]);

        $staff = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $adminRole->id,
            'name' => 'Staff Admin',
            'email' => 'staff-'.$tenant->slug.'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $customer = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $customerRole->id,
            'name' => 'Customer User',
            'email' => 'customer-'.$tenant->slug.'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
            'is_external' => true,
            'external_party' => 'customer',
            'contractor_id' => $customerContractor->id,
        ]);

        $carrier = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $carrierRole->id,
            'name' => 'Carrier User',
            'email' => 'carrier-'.$tenant->slug.'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
            'is_external' => true,
            'external_party' => 'carrier',
            'contractor_id' => $carrierContractor->id,
        ]);

        return [
            'tenant' => $tenant,
            'staff' => $staff,
            'customer' => $customer,
            'carrier' => $carrier,
            'customerContractor' => $customerContractor,
            'carrierContractor' => $carrierContractor,
        ];
    }
}
