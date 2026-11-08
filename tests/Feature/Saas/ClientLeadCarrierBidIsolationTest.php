<?php

namespace Tests\Feature\Saas;

use App\Models\Contractor;
use App\Models\Lead;
use App\Models\LoadBoardOffer;
use App\Models\LoadBoardPost;
use App\Models\Order;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CarrierMatching\CarrierMatchingService;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\SaasTestCase;

class ClientLeadCarrierBidIsolationTest extends SaasTestCase
{
    public function test_client_cargo_creates_lead_not_published_offer(): void
    {
        $fixture = $this->seedTenantFixture('pro');
        $this->actingAsTenant($fixture['tenant'], $fixture['customer']);

        $response = $this->postJson(route('mobile.shell.counterparty.cargo-requests.store'), [
            'loading_location' => 'Москва',
            'unloading_location' => 'Казань',
            'cargo_name' => 'Паллеты',
            'cargo' => [
                'name' => 'Паллеты',
                'weight_value' => 10,
                'weight_unit' => 't',
                'length_m' => 6,
                'width_m' => 2.4,
                'height_m' => 2.5,
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('lead.loading_location', 'Москва')
            ->assertJsonPath('lead.cargo.name', 'Паллеты');

        $leadId = (int) $response->json('lead.id');
        $lead = Lead::query()->findOrFail($leadId);
        $this->assertSame($fixture['tenant']->id, (int) $lead->tenant_id);
        $this->assertSame($fixture['customerContractor']->id, (int) $lead->counterparty_id);
        $this->assertTrue(CarrierMatchingService::isCustomerRequestSource((string) $lead->source));

        $this->assertSame(0, LoadBoardPost::query()->where('lead_id', $leadId)->count());
    }

    public function test_staff_share_targets_only_selected_same_tenant_carriers(): void
    {
        $fixture = $this->seedTenantFixture('start');
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

        $lead = $this->createLeadForCustomer($fixture);

        $response = $this->postJson(route('leads.carrier-matching.share', $lead), [
            'carrier_ids' => [$fixture['carrierContractor']->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('post.invited_carrier_ids.0', $fixture['carrierContractor']->id);

        $post = LoadBoardPost::query()->findOrFail((int) $response->json('post.id'));
        $this->assertTrue((bool) data_get($post->metadata, 'carrier_matching.enabled'));
        $this->assertNull($post->customer_rate);
        $this->assertNull($post->seller_comment);
    }

    public function test_unshared_carrier_cannot_see_or_bid_on_lead(): void
    {
        $fixture = $this->seedTenantFixture('pro');
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

        $lead = $this->createLeadForCustomer($fixture);
        $share = $this->postJson(route('leads.carrier-matching.share', $lead), [
            'carrier_ids' => [$fixture['carrierContractor']->id],
        ])->assertCreated();

        $postId = (int) $share->json('post.id');

        $this->actingAsTenant($fixture['tenant'], $fixture['otherCarrier']);

        $this->getJson(route('mobile.shell.counterparty.matching-loads.index'))
            ->assertOk()
            ->assertJsonCount(0, 'posts');

        $this->getJson(route('mobile.shell.counterparty.matching-loads.show', $postId))
            ->assertNotFound();

        $this->postJson(route('mobile.shell.counterparty.matching-loads.bids.store', $postId), [
            'carrier_rate' => 50000,
        ])->assertForbidden();
    }

    public function test_carrier_bid_hidden_from_peer_carriers(): void
    {
        $fixture = $this->seedTenantFixture('pro');
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

        $lead = $this->createLeadForCustomer($fixture);
        $postId = (int) $this->postJson(route('leads.carrier-matching.share', $lead), [
            'carrier_ids' => [
                $fixture['carrierContractor']->id,
                $fixture['otherCarrierContractor']->id,
            ],
        ])->json('post.id');

        $this->actingAsTenant($fixture['tenant'], $fixture['carrier']);
        $this->postJson(route('mobile.shell.counterparty.matching-loads.bids.store', $postId), [
            'carrier_rate' => 42000,
            'carrier_rate_currency' => 'RUB',
        ])->assertCreated();

        $this->actingAsTenant($fixture['tenant'], $fixture['otherCarrier']);
        $peerPayload = $this->getJson(route('mobile.shell.counterparty.matching-loads.show', $postId))
            ->assertOk()
            ->json('post');

        $this->assertArrayNotHasKey('customer_rate', $peerPayload);
        $this->assertArrayNotHasKey('offers', $peerPayload);
        $this->assertNull($peerPayload['own_offer']);

        $this->actingAsTenant($fixture['tenant'], $fixture['carrier']);
        $ownRate = $this->getJson(route('mobile.shell.counterparty.matching-loads.show', $postId))
            ->assertOk()
            ->json('post.own_offer.carrier_rate');

        $this->assertSame(42000.0, (float) $ownRate);
    }

    public function test_lead_stays_lead_until_staff_confirm_accept_does_not_create_order(): void
    {
        $fixture = $this->seedTenantFixture('pro');
        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);

        $lead = $this->createLeadForCustomer($fixture);
        $postId = (int) $this->postJson(route('leads.carrier-matching.share', $lead), [
            'carrier_ids' => [$fixture['carrierContractor']->id],
        ])->json('post.id');

        $this->actingAsTenant($fixture['tenant'], $fixture['carrier']);
        $offerId = (int) $this->postJson(route('mobile.shell.counterparty.matching-loads.bids.store', $postId), [
            'carrier_rate' => 55500,
        ])->json('offer.id');

        $ordersBefore = Order::query()->count();

        $this->actingAsTenant($fixture['tenant'], $fixture['staff']);
        $this->postJson(route('leads.carrier-matching.offers.accept', [$lead, $offerId]))
            ->assertOk()
            ->assertJsonPath('lead.order_id', null)
            ->assertJsonPath('lead.performers.0.contractor_id', $fixture['carrierContractor']->id)
            ->assertJsonPath('lead.calculated_cost', 55500);

        $this->assertSame($ordersBefore, Order::query()->count());
        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
        $this->assertSame('closed', LoadBoardPost::query()->findOrFail($postId)->status);
        $this->assertSame('approved', LoadBoardOffer::query()->findOrFail($offerId)->status);
    }

    public function test_client_cannot_see_another_clients_lead(): void
    {
        $fixture = $this->seedTenantFixture('pro');
        $this->actingAsTenant($fixture['tenant'], $fixture['customer']);

        $own = $this->postJson(route('mobile.shell.counterparty.cargo-requests.store'), [
            'loading_location' => 'А',
            'unloading_location' => 'Б',
            'cargo' => ['name' => 'Свой'],
        ])->assertCreated()->json('lead.id');

        TenantContext::set($fixture['tenant']);
        $otherLead = Lead::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'number' => 'LD-OTHER-'.uniqid(),
            'status' => 'new',
            'source' => CarrierMatchingService::SOURCE_CUSTOMER_REQUEST,
            'counterparty_id' => $fixture['otherCustomerContractor']->id,
            'title' => 'Чужая заявка',
            'loading_location' => 'X',
            'unloading_location' => 'Y',
        ]);

        $this->actingAsTenant($fixture['tenant'], $fixture['customer']);
        $this->getJson(route('mobile.shell.counterparty.cargo-requests.show', $otherLead))
            ->assertNotFound();

        $this->getJson(route('mobile.shell.counterparty.cargo-requests.show', $own))
            ->assertOk();
    }

    public function test_carrier_matching_listing_stays_inside_tenant(): void
    {
        $tenantA = $this->seedTenantFixture('pro', 'a');
        $tenantB = $this->seedTenantFixture('pro', 'b');

        $this->actingAsTenant($tenantA['tenant'], $tenantA['staff']);
        $lead = $this->createLeadForCustomer($tenantA);
        $postId = (int) $this->postJson(route('leads.carrier-matching.share', $lead), [
            'carrier_ids' => [$tenantA['carrierContractor']->id],
        ])->json('post.id');

        $this->actingAsTenant($tenantB['tenant'], $tenantB['carrier']);
        $this->getJson(route('mobile.shell.counterparty.matching-loads.index'))
            ->assertOk()
            ->assertJsonCount(0, 'posts');

        $this->getJson(route('mobile.shell.counterparty.matching-loads.show', $postId))
            ->assertNotFound();
    }

    public function test_start_plan_enables_carrier_matching_not_load_board(): void
    {
        TenantContext::bypass(true);
        $tenant = Tenant::query()->create([
            'slug' => 'cm-start-'.uniqid(),
            'name' => 'CM Start',
            'status' => 'active',
            'plan' => 'start',
        ]);
        TenantContext::clear();

        $this->assertTrue($tenant->featureEnabled('carrier_matching'));
        $this->assertFalse($tenant->featureEnabled('load_board'));
        $this->assertFalse($tenant->featureEnabled('traklo_mobile'));
    }

    /**
     * @return array<string, mixed>
     */
    private function seedTenantFixture(string $plan, string $suffix = ''): array
    {
        if (! Schema::hasColumn('users', 'is_external')) {
            $this->markTestSkipped('users.is_external migration is not applied.');
        }

        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'cm-'.$plan.'-'.$suffix.uniqid(),
            'name' => 'Carrier Matching '.$plan,
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

        $carrierRole = Role::query()->firstOrCreate(
            ['name' => 'counterparty_carrier'],
            [
                'display_name' => 'Carrier',
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
        $otherCustomerContractor = Contractor::query()->create([
            'tenant_id' => $tenant->id,
            'type' => 'customer',
            'name' => 'Other Customer Co',
            'is_active' => true,
        ]);
        $carrierContractor = Contractor::query()->create([
            'tenant_id' => $tenant->id,
            'type' => 'carrier',
            'name' => 'Carrier Co',
            'is_active' => true,
        ]);
        $otherCarrierContractor = Contractor::query()->create([
            'tenant_id' => $tenant->id,
            'type' => 'carrier',
            'name' => 'Other Carrier Co',
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

        $otherCarrier = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $carrierRole->id,
            'name' => 'Other Carrier User',
            'email' => 'carrier2-'.$tenant->slug.'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
            'is_external' => true,
            'external_party' => 'carrier',
            'contractor_id' => $otherCarrierContractor->id,
        ]);

        return [
            'tenant' => $tenant,
            'staff' => $staff,
            'customer' => $customer,
            'carrier' => $carrier,
            'otherCarrier' => $otherCarrier,
            'customerContractor' => $customerContractor,
            'otherCustomerContractor' => $otherCustomerContractor,
            'carrierContractor' => $carrierContractor,
            'otherCarrierContractor' => $otherCarrierContractor,
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
     */
    private function createLeadForCustomer(array $fixture): Lead
    {
        TenantContext::set($fixture['tenant']);

        return Lead::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'number' => 'LD-CM-'.uniqid(),
            'status' => 'new',
            'source' => CarrierMatchingService::SOURCE_CUSTOMER_REQUEST,
            'counterparty_id' => $fixture['customerContractor']->id,
            'title' => 'Тестовая заявка',
            'loading_location' => 'Москва',
            'unloading_location' => 'СПб',
            'created_by' => $fixture['customer']->id,
        ]);
    }
}
