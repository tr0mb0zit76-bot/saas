<?php

namespace Tests\Feature\Saas;

use App\Models\Order;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\OrderViewAuthorization;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Tests\SaasTestCase;

/**
 * Gate tests for saas-audit-remediation P0.10 — order visibility via authorization helpers.
 */
class SaasAuditGateTest extends SaasTestCase
{
    public function test_manager_cannot_view_peer_order_record(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'audit-'.uniqid(),
            'name' => 'Audit Co',
            'status' => 'active',
            'plan' => 'pro',
        ]);

        $managerRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'manager',
            'display_name' => 'Manager',
            'permissions' => RoleAccess::permissionKeys(),
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('manager'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('manager'),
        ]);

        $managerA = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $managerRole->id,
            'name' => 'Manager A',
            'email' => 'mgr-a-'.uniqid().'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $managerB = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $managerRole->id,
            'name' => 'Manager B',
            'email' => 'mgr-b-'.uniqid().'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(false);
        TenantContext::set($tenant);

        $orderB = Order::query()->create([
            'tenant_id' => $tenant->id,
            'order_number' => 'AUD-B-001',
            'company_code' => 'AUD',
            'manager_id' => $managerB->id,
            'status' => 'new',
            'is_active' => true,
        ]);

        $this->assertFalse(OrderViewAuthorization::userCanViewOrder($managerA, $orderB));
        $this->assertTrue(OrderViewAuthorization::userCanViewOrder($managerB, $orderB));
    }
}
