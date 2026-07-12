<?php

namespace Tests\Feature\Saas;

use App\Models\Order;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Saas\TenantProvisioner;
use App\Services\Saas\TenantUsageLimiter;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\SaasTestCase;

class TenantUsageLimiterTest extends SaasTestCase
{
    public function test_user_limit_blocks_creation_on_start_plan(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'limit-users-'.uniqid(),
            'name' => 'Limit Users',
            'status' => 'active',
            'plan' => 'start',
        ]);

        app(TenantProvisioner::class)->provision($tenant);

        $adminRole = Role::query()->where('tenant_id', $tenant->id)->where('name', 'admin')->first();

        for ($i = 0; $i < 5; $i++) {
            User::query()->create([
                'tenant_id' => $tenant->id,
                'role_id' => $adminRole->id,
                'name' => "User {$i}",
                'email' => "user-{$i}-".uniqid().'@saas.local',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
        }

        TenantContext::bypass(false);
        TenantContext::set($tenant);

        $this->expectException(ValidationException::class);

        app(TenantUsageLimiter::class)->assertCanAddUser();
    }

    public function test_order_limit_blocks_creation_on_start_plan(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'limit-orders-'.uniqid(),
            'name' => 'Limit Orders',
            'status' => 'active',
            'plan' => 'start',
        ]);

        app(TenantProvisioner::class)->provision($tenant);

        $adminRole = Role::query()->where('tenant_id', $tenant->id)->where('name', 'admin')->first();

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $adminRole->id,
            'name' => 'Admin',
            'email' => 'admin-limit-'.uniqid().'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        for ($i = 0; $i < 200; $i++) {
            Order::query()->create([
                'tenant_id' => $tenant->id,
                'order_number' => 'LIM-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'company_code' => 'LIM',
                'manager_id' => $user->id,
                'status' => 'draft',
                'is_active' => true,
                'created_at' => now(),
            ]);
        }

        TenantContext::bypass(false);
        TenantContext::set($tenant);

        $this->expectException(ValidationException::class);

        app(TenantUsageLimiter::class)->assertCanCreateOrder();
    }

    public function test_enterprise_plan_has_no_user_limit(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'no-limit-'.uniqid(),
            'name' => 'Enterprise',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        app(TenantProvisioner::class)->provision($tenant);

        $adminRole = Role::query()->where('tenant_id', $tenant->id)->where('name', 'admin')->first();

        for ($i = 0; $i < 10; $i++) {
            User::query()->create([
                'tenant_id' => $tenant->id,
                'role_id' => $adminRole->id,
                'name' => "User {$i}",
                'email' => "ent-{$i}-".uniqid().'@saas.local',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
        }

        TenantContext::bypass(false);
        TenantContext::set($tenant);

        app(TenantUsageLimiter::class)->assertCanAddUser();

        $this->assertTrue(true);
    }
}
