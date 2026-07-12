<?php

namespace Tests\Feature\Saas;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUsageLog;
use App\Models\User;
use App\Services\Saas\TenantUsageMeter;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\SaasTestCase;

class TenantUsageMeterTest extends SaasTestCase
{
    public function test_record_usage_creates_daily_log(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'usage-'.uniqid(),
            'name' => 'Usage Co',
            'status' => 'active',
            'plan' => 'start',
        ]);

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'admin',
            'display_name' => 'Admin',
            'permissions' => RoleAccess::permissionKeys(),
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('admin'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('admin'),
        ]);

        User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => 'Admin',
            'email' => 'usage-'.uniqid().'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(false);

        Artisan::call('saas:record-usage');

        TenantContext::bypass(true);
        $log = TenantUsageLog::query()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($log);
        $this->assertSame(1, $log->users_count);
        TenantContext::bypass(false);
    }

    public function test_storage_limit_blocks_tenant_storage_put(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'storage-'.uniqid(),
            'name' => 'Storage Co',
            'status' => 'active',
            'plan' => 'start',
        ]);

        \App\Models\SubscriptionPlan::query()->updateOrCreate(
            ['key' => 'start'],
            [
                'label' => 'Start',
                'features' => [],
                'limits' => ['users' => 5, 'orders_per_month' => 200, 'storage_mb' => 0],
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        TenantContext::bypass(false);
        TenantContext::set($tenant->fresh());

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        \App\Support\TenantStorage::put('test/large.bin', str_repeat('x', 1024));
    }
}
