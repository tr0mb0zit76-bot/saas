<?php

namespace Tests\Unit\Support;

use App\Models\Tenant;
use App\Support\TenantContext;
use PHPUnit\Framework\TestCase;

class TenantContextBypassTest extends TestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_set_tenant_clears_sticky_bypass(): void
    {
        TenantContext::clear();
        TenantContext::bypass(true);

        $tenant = new Tenant;
        $tenant->forceFill([
            'slug' => 'bypass-demo',
            'name' => 'Bypass clear',
            'status' => 'active',
            'plan' => 'start',
        ]);
        $tenant->id = 42;

        $this->assertTrue(TenantContext::isBypassed());

        TenantContext::set($tenant);

        $this->assertFalse(TenantContext::isBypassed());
        $this->assertSame(42, TenantContext::id());
    }
}
