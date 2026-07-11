<?php

namespace Tests\Feature\Saas;

use App\Models\SalesScript;
use App\Models\Tenant;
use App\Support\TenantContext;
use Tests\SaasTestCase;

class TenantTierBIsolationTest extends SaasTestCase
{
    public function test_sales_scripts_are_isolated_between_tenants(): void
    {
        TenantContext::bypass(true);

        $tenantA = Tenant::query()->create([
            'slug' => 'tier-b-a-'.uniqid(),
            'name' => 'Tier B A',
            'status' => 'active',
            'plan' => 'pro',
        ]);

        $tenantB = Tenant::query()->create([
            'slug' => 'tier-b-b-'.uniqid(),
            'name' => 'Tier B B',
            'status' => 'active',
            'plan' => 'pro',
        ]);

        TenantContext::bypass(false);

        TenantContext::set($tenantA);
        $script = SalesScript::query()->create([
            'tenant_id' => $tenantA->id,
            'title' => 'Hidden script',
            'description' => null,
            'channel' => 'phone',
            'tags' => null,
        ]);

        TenantContext::set($tenantB);
        $this->assertNull(SalesScript::query()->find($script->id));
    }
}
