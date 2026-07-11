<?php

namespace Tests\Feature\Saas;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Tests\SaasTestCase;

class TenantEmailUniquenessTest extends SaasTestCase
{
    public function test_same_email_can_exist_in_different_tenants(): void
    {
        TenantContext::bypass(true);

        $tenantA = Tenant::query()->create([
            'slug' => 'email-a-'.uniqid(),
            'name' => 'Email A',
            'status' => 'active',
            'plan' => 'start',
        ]);

        $tenantB = Tenant::query()->create([
            'slug' => 'email-b-'.uniqid(),
            'name' => 'Email B',
            'status' => 'active',
            'plan' => 'start',
        ]);

        $role = Role::query()->firstOrCreate(
            ['name' => 'manager', 'tenant_id' => $tenantA->id],
            [
                'tenant_id' => $tenantA->id,
                'display_name' => 'Manager',
                'permissions' => RoleAccess::permissionKeys(),
                'visibility_areas' => RoleAccess::defaultVisibilityAreas('manager'),
                'visibility_scopes' => RoleAccess::defaultVisibilityScopes('manager'),
            ],
        );

        $email = 'shared-'.uniqid().'@saas.local';

        User::query()->create([
            'tenant_id' => $tenantA->id,
            'role_id' => $role->id,
            'name' => 'User A',
            'email' => $email,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $roleB = Role::query()->firstOrCreate(
            ['name' => 'manager', 'tenant_id' => $tenantB->id],
            [
                'tenant_id' => $tenantB->id,
                'display_name' => 'Manager',
                'permissions' => RoleAccess::permissionKeys(),
                'visibility_areas' => RoleAccess::defaultVisibilityAreas('manager'),
                'visibility_scopes' => RoleAccess::defaultVisibilityScopes('manager'),
            ],
        );

        User::query()->create([
            'tenant_id' => $tenantB->id,
            'role_id' => $roleB->id,
            'name' => 'User B',
            'email' => $email,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(false);

        TenantContext::set($tenantA);
        $this->assertSame(1, User::query()->where('email', $email)->count());

        TenantContext::set($tenantB);
        $this->assertSame(1, User::query()->where('email', $email)->count());
    }
}
