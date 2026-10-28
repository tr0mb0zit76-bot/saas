<?php

namespace Tests\Feature\Documents;

use App\Models\Order;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Tests\SaasTestCase;

class DocumentRegistryEnteredIn1CTest extends SaasTestCase
{
    public function test_entered_in_1c_is_forbidden_without_integrations_feature(): void
    {
        [$user, $tenant] = $this->makeTenantUser('start');

        $order = $this->createOrderFor($user, $tenant, ['order_number' => 'DOC-1C-BLOCKED']);

        config(['saas.default_tenant_slug' => $tenant->slug]);

        $this->actingAs($user)
            ->patchJson(route('documents.orders.entered-in-1c', $order), [
                'entered_in_1c' => 'да',
            ])
            ->assertForbidden();
    }

    public function test_manager_can_mark_order_as_entered_in_1c(): void
    {
        [$user, $tenant] = $this->makeTenantUser('enterprise');

        $order = $this->createOrderFor($user, $tenant, ['order_number' => 'DOC-1C-1']);

        config(['saas.default_tenant_slug' => $tenant->slug]);

        $this->actingAs($user)
            ->patchJson(route('documents.orders.entered-in-1c', $order), [
                'entered_in_1c' => 'да',
            ])
            ->assertOk()
            ->assertJson(['entered_in_1c' => 'да']);

        $order->refresh();

        $this->assertNotNull($order->accounting_handoff_at);
        $this->assertSame($user->id, $order->accounting_handoff_by);
    }

    public function test_manager_can_clear_entered_in_1c_flag(): void
    {
        [$user, $tenant] = $this->makeTenantUser('enterprise');

        $order = $this->createOrderFor($user, $tenant, [
            'accounting_handoff_at' => now(),
            'accounting_handoff_by' => $user->id,
        ]);

        config(['saas.default_tenant_slug' => $tenant->slug]);

        $this->actingAs($user)
            ->patchJson(route('documents.orders.entered-in-1c', $order), [
                'entered_in_1c' => 'нет',
            ])
            ->assertOk()
            ->assertJson(['entered_in_1c' => 'нет']);

        $order->refresh();

        $this->assertNull($order->accounting_handoff_at);
        $this->assertNull($order->accounting_handoff_by);
    }

    public function test_foreign_manager_cannot_update_entered_in_1c(): void
    {
        [$manager, $tenant] = $this->makeTenantUser('enterprise');
        [$otherManager] = $this->makeTenantUser('enterprise', $tenant);

        $order = $this->createOrderFor($otherManager, $tenant, ['order_number' => 'DOC-1C-FOREIGN']);

        config(['saas.default_tenant_slug' => $tenant->slug]);

        $this->actingAs($manager)
            ->patchJson(route('documents.orders.entered-in-1c', $order), [
                'entered_in_1c' => 'да',
            ])
            ->assertForbidden();
    }

    public function test_documents_index_includes_entered_in_1c_payload(): void
    {
        [$user, $tenant] = $this->makeTenantUser('enterprise');

        $order = $this->createOrderFor($user, $tenant, [
            'accounting_handoff_at' => now(),
            'accounting_handoff_by' => $user->id,
        ]);

        config(['saas.default_tenant_slug' => $tenant->slug]);

        $this->actingAs($user)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.order_id', $order->id)
                ->where('rows.0.entered_in_1c', 'да'),
            );
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function makeTenantUser(string $plan, ?Tenant $tenant = null): array
    {
        TenantContext::bypass(true);

        $tenant ??= Tenant::query()->create([
            'slug' => 'doc1c-'.uniqid(),
            'name' => 'Doc 1C Tenant',
            'status' => 'active',
            'plan' => $plan,
            'settings' => ['onboarding' => ['completed_at' => now()->toIso8601String()]],
        ]);

        $role = Role::query()->firstOrCreate(
            ['name' => 'manager', 'tenant_id' => $tenant->id],
            [
                'display_name' => 'Manager',
                'permissions' => [],
                'visibility_areas' => ['documents', 'orders'],
                'visibility_scopes' => ['documents' => 'own', 'orders' => 'own'],
            ],
        );

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => 'Manager',
            'email' => 'mgr-'.uniqid().'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(false);

        return [$user, $tenant];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createOrderFor(User $user, Tenant $tenant, array $attributes = []): Order
    {
        return TenantContext::runAs($tenant, function () use ($user, $attributes): Order {
            return Order::factory()->create(array_merge([
                'tenant_id' => $user->tenant_id,
                'manager_id' => $user->id,
            ], $attributes));
        });
    }
}
