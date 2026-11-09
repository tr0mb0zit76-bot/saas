<?php

namespace Tests\Feature\Saas;

use App\Contracts\Inference\ChatCompletionClient;
use App\Models\Contractor;
use App\Models\ContractorContact;
use App\Models\Conversation;
use App\Models\ExternalUserInvite;
use App\Models\FleetVehicle;
use App\Models\FleetVehicleDocument;
use App\Models\Order;
use App\Models\OrderPortalInvite;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ExternalUsers\CounterpartyOrderDocumentService;
use App\Services\ExternalUsers\ExternalUserInviteService;
use App\Services\ExternalUsers\ExternalUserProvisionService;
use App\Services\Mcp\McpAccessGate;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use App\Support\TenantStorage;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\SaasTestCase;

/**
 * P0 gates from saas-security audit: fleet private storage, customer LLM block,
 * production apex tenant fail-closed, MCP_DEV_USER_ID lab-only, carrier DTO strip,
 * plus invite reuse + messenger participant ACL.
 */
class SecurityP0IsolationTest extends SaasTestCase
{
    public function test_production_apex_does_not_fallback_to_default_tenant_slug(): void
    {
        TenantContext::bypass(true);

        $demo = Tenant::query()->create([
            'slug' => 'demo-apex-'.uniqid(),
            'name' => 'Demo Tenant',
            'status' => 'active',
            'plan' => 'start',
        ]);

        TenantContext::bypass(false);

        config(['saas.default_tenant_slug' => $demo->slug]);
        config(['app.platform_domain' => 'platform.traklo.pro']);

        $previous = $this->app->make('env');
        $this->app->instance('env', 'production');

        try {
            $this->get('http://traklo.pro/login')->assertNotFound();
            $this->get('http://www.traklo.pro/login')->assertNotFound();
        } finally {
            $this->app->instance('env', $previous);
        }
    }

    public function test_customer_cargo_parse_stays_heuristic_and_skips_external_llm(): void
    {
        $fixture = $this->seedTenantFixture('pro');

        $chat = Mockery::mock(ChatCompletionClient::class);
        $chat->shouldNotReceive('chat');
        $this->app->instance(ChatCompletionClient::class, $chat);

        config(['ai.order_intake.enabled' => true]);

        $this->actingAsTenant($fixture['tenant'], $fixture['customer']);

        $response = $this->postJson(route('mobile.shell.counterparty.cargo-requests.parse'), [
            'message' => 'Нужна перевозка Москва — Казань, 10 тонн паллет, ИНН 7707083893, контакт Иван Иванов +79001112233',
        ]);

        $response->assertOk()
            ->assertJsonPath('parser', 'heuristic');
    }

    public function test_consumed_external_invite_cannot_be_reused(): void
    {
        if (! Schema::hasTable('external_user_invites')) {
            $this->markTestSkipped('external_user_invites missing');
        }

        $fixture = $this->seedTenantFixture('pro');
        TenantContext::set($fixture['tenant']);
        config(['saas.default_tenant_slug' => $fixture['tenant']->slug]);

        $contact = ContractorContact::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'contractor_id' => $fixture['carrierContractor']->id,
            'full_name' => 'Carrier Contact',
            'email' => 'invite-reuse-'.uniqid().'@saas.local',
            'phone' => '+79001110001',
            'is_primary' => true,
        ]);

        $payload = app(ExternalUserProvisionService::class)->provisionInvite(
            $fixture['carrierContractor'],
            $contact,
            $fixture['staff'],
        );

        $token = $payload['token'];

        $this->post(route('external.invite.store', ['token' => $token]), [
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect(route('mobile.messenger.app'));

        $invite = ExternalUserInvite::query()->findOrFail($payload['invite']->id);
        $this->assertNotNull($invite->fresh()->consumed_at);

        $this->get(route('external.invite.show', ['token' => $token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('External/InviteExpired'));

        $this->post(route('external.invite.store', ['token' => $token]), [
            'password' => 'Password2!',
            'password_confirmation' => 'Password2!',
        ])->assertStatus(410);
    }

    public function test_expired_external_invite_is_rejected(): void
    {
        if (! Schema::hasTable('external_user_invites')) {
            $this->markTestSkipped('external_user_invites missing');
        }

        $fixture = $this->seedTenantFixture('pro');
        TenantContext::set($fixture['tenant']);
        config(['saas.default_tenant_slug' => $fixture['tenant']->slug]);

        $contact = ContractorContact::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'contractor_id' => $fixture['carrierContractor']->id,
            'full_name' => 'Expired Contact',
            'email' => 'invite-exp-'.uniqid().'@saas.local',
            'is_primary' => true,
        ]);

        $created = app(ExternalUserInviteService::class)
            ->createInvite($contact, 'carrier', $fixture['staff'], $fixture['carrier']);
        $created['invite']->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->get(route('external.invite.show', ['token' => $created['token']]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('External/InviteExpired'));
    }

    public function test_non_participant_cannot_read_or_post_messenger_messages(): void
    {
        $fixture = $this->seedTenantFixture('pro');
        TenantContext::set($fixture['tenant']);

        $conversation = Conversation::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'type' => 'direct',
            'created_by' => $fixture['staff']->id,
        ]);
        $conversation->participants()->attach([
            $fixture['staff']->id,
            $fixture['customer']->id,
        ]);

        $this->actingAsTenant($fixture['tenant'], $fixture['carrier']);

        $this->getJson(route('messenger.conversations.messages', $conversation))
            ->assertForbidden();

        $this->postJson(route('messenger.conversations.messages.store', $conversation), [
            'body' => 'IDOR probe',
        ])->assertForbidden();
    }

    public function test_fleet_vehicle_document_download_is_isolated_across_tenants(): void
    {
        if (! Schema::hasTable('fleet_vehicle_documents')) {
            $this->markTestSkipped('fleet_vehicle_documents missing');
        }

        $tenantA = $this->seedTenantFixture('enterprise', 'a');
        $tenantB = $this->seedTenantFixture('enterprise', 'b');

        TenantContext::set($tenantA['tenant']);
        $vehicle = FleetVehicle::query()->create([
            'tenant_id' => $tenantA['tenant']->id,
            'owner_contractor_id' => $tenantA['carrierContractor']->id,
            'tractor_plate' => 'A123AA77',
            'tractor_brand' => 'Volvo',
        ]);

        $relative = 'fleet/vehicles/'.$vehicle->id.'/secret.txt';
        TenantStorage::put($relative, 'tenant-a-secret');

        $document = FleetVehicleDocument::query()->create([
            'tenant_id' => $tenantA['tenant']->id,
            'fleet_vehicle_id' => $vehicle->id,
            'document_type' => 'pts',
            'disk' => TenantStorage::diskName(),
            'path' => TenantStorage::path($relative),
            'original_name' => 'secret.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 14,
            'uploaded_by' => $tenantA['staff']->id,
        ]);

        $this->assertNotSame('public', $document->disk);

        $this->actingAsTenant($tenantB['tenant'], $tenantB['staff']);

        $this->get(route('fleet.vehicles.documents.download', [
            'fleetVehicle' => $vehicle->id,
            'fleetVehicleDocument' => $document->id,
        ]))->assertNotFound();
    }

    public function test_mcp_dev_user_id_is_ignored_outside_local_and_testing(): void
    {
        $fixture = $this->seedTenantFixture('pro');
        TenantContext::set($fixture['tenant']);

        config(['mcp.dev_user_id' => (string) $fixture['staff']->id]);

        $previous = $this->app->make('env');
        $this->app->instance('env', 'production');

        try {
            app(McpAccessGate::class)->resolveUser();
            $this->fail('Expected AuthenticationException when MCP_DEV_USER_ID is set in production.');
        } catch (AuthenticationException) {
            $this->addToAssertionCount(1);
        } finally {
            $this->app->instance('env', $previous);
        }
    }

    public function test_carrier_document_slots_omit_customer_name(): void
    {
        $fixture = $this->seedTenantFixture('pro');
        TenantContext::set($fixture['tenant']);

        $customer = $fixture['customerContractor'];
        $customer->forceFill(['name' => 'ООО Секретный Заказчик'])->save();

        $order = Order::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'order_number' => 'SEC-'.uniqid(),
            'company_code' => 'SEC',
            'manager_id' => $fixture['staff']->id,
            'customer_id' => $customer->id,
            'carrier_id' => $fixture['carrierContractor']->id,
            'status' => 'new',
            'is_active' => true,
        ]);

        OrderPortalInvite::query()->create([
            'order_id' => $order->id,
            'contractor_id' => $fixture['carrierContractor']->id,
            'stage' => 'leg_1',
            'carrier_slot' => 1,
            'purpose' => OrderPortalInvite::PURPOSE_CARRIER_FLEET,
            'token_hash' => hash('sha256', 'sec-p0-'.uniqid()),
            'created_by' => $fixture['staff']->id,
            'expires_at' => now()->addDay(),
        ]);

        $payload = app(CounterpartyOrderDocumentService::class)
            ->documentSlotsForUser($fixture['carrier'], $order);

        $this->assertArrayNotHasKey('customer_name', $payload['order']);
        $this->assertSame($order->id, $payload['order']['id']);
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

        $slug = 'sec-p0-'.$plan.($suffix !== '' ? '-'.$suffix : '').'-'.uniqid();

        $tenant = Tenant::query()->create([
            'slug' => $slug,
            'name' => 'Security P0 '.$slug,
            'status' => 'active',
            'plan' => $plan,
            'settings' => ['onboarding' => ['completed_at' => now()->toIso8601String()]],
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

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        config(['saas.default_tenant_slug' => $tenant->slug]);
        TenantContext::set($tenant);
        $this->actingAs($user);

        return $this;
    }
}
