<?php

namespace Tests\Feature\Saas;

use App\Models\Contractor;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CarrierMatching\CarrierMatchingService;
use App\Services\LeadConversionService;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\SaasTestCase;

class ClientLeadQuoteLifecycleTest extends SaasTestCase
{
    public function test_draft_quote_is_hidden_from_customer_dto(): void
    {
        $fixture = $this->seedTenantFixture('pro');
        $lead = $this->createCustomerLead($fixture, [
            'quoted_price' => 120000,
            'quoted_currency' => 'RUB',
            'quote_status' => 'draft',
            'calculated_cost' => 80000,
            'performers' => [[
                'stage' => 'leg_1',
                'contractor_id' => $fixture['carrierContractor']->id,
                'contractor_name' => 'Carrier Co',
                'estimated_cost' => 80000,
            ]],
        ]);

        $this->actingAsTenant($fixture['tenant'], $fixture['customer']);

        $payload = $this->getJson(route('mobile.shell.counterparty.cargo-requests.show', $lead))
            ->assertOk()
            ->json('lead');

        $this->assertSame('draft', $payload['quote_status']);
        $this->assertArrayNotHasKey('quoted_price', $payload);
        $this->assertArrayNotHasKey('quoted_currency', $payload);
        $this->assertArrayNotHasKey('calculated_cost', $payload);
        $this->assertArrayNotHasKey('performers', $payload);
        $this->assertArrayNotHasKey('carrier_rate', $payload);
        $this->assertArrayNotHasKey('offers', $payload);
    }

    public function test_sent_quote_is_visible_without_carrier_rates(): void
    {
        $fixture = $this->seedTenantFixture('pro');
        $lead = $this->createCustomerLead($fixture, [
            'quoted_price' => 135000,
            'quoted_currency' => 'RUB',
            'quote_status' => 'sent',
            'quote_sent_at' => now(),
            'calculated_cost' => 90000,
        ]);

        $this->actingAsTenant($fixture['tenant'], $fixture['customer']);

        $payload = $this->getJson(route('mobile.shell.counterparty.cargo-requests.show', $lead))
            ->assertOk()
            ->assertJsonPath('lead.quoted_price', 135000)
            ->assertJsonPath('lead.quoted_currency', 'RUB')
            ->assertJsonPath('lead.quote_status', 'sent')
            ->json('lead');

        $this->assertArrayNotHasKey('calculated_cost', $payload);
        $this->assertArrayNotHasKey('performers', $payload);
        $this->assertArrayNotHasKey('carrier_rate', $payload);
        $this->assertArrayNotHasKey('target_price', $payload);
    }

    public function test_staff_quote_send_and_agree_flow(): void
    {
        $fixture = $this->seedTenantFixture('pro');
        $lead = $this->createCustomerLead($fixture);
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

        $this->postJson(route('leads.carrier-matching.quote.save', $lead), [
            'quoted_price' => 150000,
            'quoted_currency' => 'RUB',
        ])->assertOk()
            ->assertJsonPath('lead.quote_status', 'draft')
            ->assertJsonPath('lead.quoted_price', 150000);

        $this->postJson(route('leads.carrier-matching.quote.send', $lead))
            ->assertOk()
            ->assertJsonPath('lead.quote_status', 'sent');

        $this->assertNotNull($lead->fresh()->quote_sent_at);

        $this->postJson(route('leads.carrier-matching.quote.agree', $lead))
            ->assertOk()
            ->assertJsonPath('lead.quote_status', 'agreed');

        $this->assertNotNull($lead->fresh()->quote_agreed_at);

        $this->actingAsTenant($fixture['tenant'], $fixture['customer']);
        $this->getJson(route('mobile.shell.counterparty.cargo-requests.show', $lead))
            ->assertOk()
            ->assertJsonPath('lead.quoted_price', 150000)
            ->assertJsonPath('lead.quote_status', 'agreed');
    }

    public function test_customer_request_cannot_convert_until_quote_agreed(): void
    {
        $fixture = $this->seedTenantFixture('pro');
        $lead = $this->createCustomerLead($fixture, [
            'quoted_price' => 110000,
            'quote_status' => 'sent',
            'quote_sent_at' => now(),
        ]);

        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);
        $ordersBefore = Order::query()->count();

        $this->assertFalse(CarrierMatchingService::quoteAllowsOrderConversion($lead));

        try {
            app(LeadConversionService::class)->convert($lead, $fixture['staff']);
            $this->fail('Expected ValidationException when quote is not agreed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quote_status', $exception->errors());
        }

        $this->assertSame($ordersBefore, Order::query()->count());

        $lead->forceFill([
            'quote_status' => 'agreed',
            'quote_agreed_at' => now(),
        ])->save();

        $this->assertTrue(CarrierMatchingService::quoteAllowsOrderConversion($lead->fresh()));
    }

    public function test_quote_actions_stay_inside_tenant(): void
    {
        $a = $this->seedTenantFixture('pro', 'a');
        $b = $this->seedTenantFixture('pro', 'b');
        $leadB = $this->createCustomerLead($b);

        $this->actingAsTenant($a['tenant'], $a['staff']);

        $this->postJson(route('leads.carrier-matching.quote.save', $leadB), [
            'quoted_price' => 99999,
        ])->assertNotFound();

        $this->assertSame('none', (string) ($leadB->fresh()->quote_status ?: 'none'));
        $this->assertNull($leadB->fresh()->quoted_price);
    }

    public function test_customer_cannot_call_staff_quote_endpoints(): void
    {
        $fixture = $this->seedTenantFixture('pro');
        $lead = $this->createCustomerLead($fixture);
        $this->actingAsTenant($fixture['tenant'], $fixture['customer']);

        $this->postJson(route('leads.carrier-matching.quote.save', $lead), [
            'quoted_price' => 100000,
        ])->assertForbidden();

        $this->postJson(route('leads.carrier-matching.quote.send', $lead))->assertForbidden();
        $this->postJson(route('leads.carrier-matching.quote.agree', $lead))->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function seedTenantFixture(string $plan, string $suffix = ''): array
    {
        if (! Schema::hasColumn('users', 'is_external')) {
            $this->markTestSkipped('users.is_external migration is not applied.');
        }

        if (! Schema::hasColumn('leads', 'quoted_price')) {
            $this->markTestSkipped('leads quote columns migration is not applied.');
        }

        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'cq-'.$plan.'-'.$suffix.uniqid(),
            'name' => 'Client Quote '.$plan,
            'status' => 'active',
            'plan' => $plan,
            'settings' => ['onboarding' => ['completed_at' => now()->toIso8601String()]],
        ]);

        $staffRole = Role::query()->firstOrCreate(
            ['name' => 'admin', 'tenant_id' => $tenant->id],
            [
                'display_name' => 'Admin',
                'permissions' => RoleAccess::permissionKeys(),
                'visibility_areas' => RoleAccess::defaultVisibilityAreas('admin'),
                'visibility_scopes' => RoleAccess::defaultVisibilityScopes('admin'),
            ],
        );

        $customerRole = Role::query()->firstOrCreate(
            ['name' => 'counterparty_customer'],
            [
                'display_name' => 'Customer',
                'permissions' => [],
                'visibility_areas' => ['counterparty_orders', 'counterparty_documents', 'counterparty_portal'],
                'visibility_scopes' => [],
            ],
        );

        $staff = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $staffRole->id,
            'name' => 'Staff',
            'email' => 'staff-'.$tenant->slug.'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
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

        return [
            'tenant' => $tenant,
            'staff' => $staff,
            'customer' => $customer,
            'customerContractor' => $customerContractor,
            'carrierContractor' => $carrierContractor,
        ];
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        config(['saas.default_tenant_slug' => $tenant->slug]);
        TenantContext::set($tenant);
        $this->actingAs($user);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  array<string, mixed>  $overrides
     */
    private function createCustomerLead(array $fixture, array $overrides = []): Lead
    {
        TenantContext::set($fixture['tenant']);

        return Lead::query()->create(array_merge([
            'tenant_id' => $fixture['tenant']->id,
            'number' => 'LD-CQ-'.uniqid(),
            'status' => 'new',
            'source' => CarrierMatchingService::SOURCE_CUSTOMER_REQUEST,
            'counterparty_id' => $fixture['customerContractor']->id,
            'title' => 'Просчёт клиента',
            'loading_location' => 'Москва',
            'unloading_location' => 'Казань',
            'quote_status' => 'none',
            'quoted_currency' => 'RUB',
            'created_by' => $fixture['customer']->id,
        ], $overrides));
    }
}
