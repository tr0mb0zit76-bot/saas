<?php

namespace Tests\Feature\Saas;

use App\Models\Task;
use App\Models\Tenant;
use App\Support\TenantContext;
use Tests\SaasTestCase;

class TenantTierAIsolationTest extends SaasTestCase
{
    public function test_tasks_are_isolated_between_tenants(): void
    {
        TenantContext::bypass(true);

        $tenantA = Tenant::query()->create([
            'slug' => 'tier-a-'.uniqid(),
            'name' => 'Tier A',
            'status' => 'active',
            'plan' => 'start',
        ]);

        $tenantB = Tenant::query()->create([
            'slug' => 'tier-b-'.uniqid(),
            'name' => 'Tier B',
            'status' => 'active',
            'plan' => 'start',
        ]);

        TenantContext::bypass(false);

        TenantContext::set($tenantA);
        $task = Task::query()->create([
            'tenant_id' => $tenantA->id,
            'number' => 'T-ISO-'.uniqid(),
            'title' => 'Hidden task',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        TenantContext::set($tenantB);
        $this->assertNull(Task::query()->find($task->id));
    }
}
