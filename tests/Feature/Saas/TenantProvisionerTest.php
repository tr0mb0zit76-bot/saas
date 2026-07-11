<?php

namespace Tests\Feature\Saas;

use App\Models\Role;
use App\Models\Tenant;
use App\Services\Saas\TenantBillingService;
use App\Services\Saas\TenantProvisioner;
use App\Support\TenantContext;
use Tests\SaasTestCase;

class TenantProvisionerTest extends SaasTestCase
{
    public function test_provisioner_seeds_roles_per_tenant(): void
    {
        TenantContext::bypass(true);

        $tenantA = Tenant::query()->create([
            'slug' => 'prov-a-'.uniqid(),
            'name' => 'Provision A',
            'status' => 'trial',
            'plan' => 'start',
        ]);

        $tenantB = Tenant::query()->create([
            'slug' => 'prov-b-'.uniqid(),
            'name' => 'Provision B',
            'status' => 'trial',
            'plan' => 'start',
        ]);

        $provisioner = app(TenantProvisioner::class);
        $provisioner->provision($tenantA);
        $provisioner->provision($tenantB);

        TenantContext::bypass(false);

        TenantContext::set($tenantA);
        $this->assertSame(7, Role::query()->count());
        $this->assertTrue(Role::query()->where('name', 'admin')->exists());

        TenantContext::set($tenantB);
        $this->assertSame(7, Role::query()->count());

        TenantContext::bypass(true);
        $this->assertSame(
            14,
            Role::query()->withoutGlobalScopes()->whereIn('tenant_id', [$tenantA->id, $tenantB->id])->count(),
        );
        TenantContext::bypass(false);
    }
}
