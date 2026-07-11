<?php

namespace Tests\Feature\Saas;

use App\Models\Contractor;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Tests\SaasTestCase;

class TenantIsolationTest extends SaasTestCase
{
    public function test_orders_are_isolated_between_tenants(): void
    {
        TenantContext::bypass(true);

        $tenantA = Tenant::query()->create([
            'slug' => 'iso-a-'.uniqid(),
            'name' => 'Isolation A',
            'status' => 'active',
            'plan' => 'start',
        ]);

        $tenantB = Tenant::query()->create([
            'slug' => 'iso-b-'.uniqid(),
            'name' => 'Isolation B',
            'status' => 'active',
            'plan' => 'start',
        ]);

        TenantContext::bypass(false);

        TenantContext::set($tenantA);
        $orderAId = $this->insertOrderRow([
            'tenant_id' => $tenantA->id,
            'order_number' => 'ISO-A-001',
            'company_code' => 'ORD',
            'manager_id' => null,
            'order_date' => now()->toDateString(),
            'status' => 'new',
            'customer_id' => null,
            'carrier_id' => null,
            'customer_rate' => 100000,
            'delta' => 10000,
            'is_active' => true,
        ]);

        TenantContext::set($tenantB);
        $this->assertNull(Order::query()->find($orderAId));

        TenantContext::bypass(false);
    }

    public function test_contractors_are_isolated_between_tenants(): void
    {
        TenantContext::bypass(true);

        $tenantA = Tenant::query()->create([
            'slug' => 'ctr-a-'.uniqid(),
            'name' => 'Contractor A tenant',
            'status' => 'active',
            'plan' => 'start',
        ]);

        $tenantB = Tenant::query()->create([
            'slug' => 'ctr-b-'.uniqid(),
            'name' => 'Contractor B tenant',
            'status' => 'active',
            'plan' => 'start',
        ]);

        TenantContext::bypass(false);

        TenantContext::set($tenantA);
        $contractor = Contractor::query()->create([
            'tenant_id' => $tenantA->id,
            'type' => 'customer',
            'name' => 'Hidden customer',
            'inn' => '7700999001',
            'is_active' => true,
        ]);

        TenantContext::set($tenantB);
        $this->assertNull(Contractor::query()->find($contractor->id));

        TenantContext::bypass(false);
    }

    public function test_login_page_resolves_default_tenant(): void
    {
        TenantContext::bypass(true);

        Tenant::query()->updateOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Demo', 'status' => 'active', 'plan' => 'start'],
        );

        TenantContext::clear();

        $response = $this->get('/login');
        $response->assertOk();

        $this->assertSame('demo', TenantContext::get()?->slug);
    }
}
