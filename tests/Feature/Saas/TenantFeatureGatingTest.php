<?php

namespace Tests\Feature\Saas;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Tests\SaasTestCase;

class TenantFeatureGatingTest extends SaasTestCase
{
    public function test_mail_route_is_blocked_on_start_plan(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'gate-'.uniqid(),
            'name' => 'Gate Test',
            'status' => 'active',
            'plan' => 'start',
        ]);

        $role = Role::query()->firstOrCreate(
            ['name' => 'admin', 'tenant_id' => $tenant->id],
            [
                'display_name' => 'Admin',
                'permissions' => RoleAccess::permissionKeys(),
                'visibility_areas' => RoleAccess::defaultVisibilityAreas('admin'),
                'visibility_scopes' => RoleAccess::defaultVisibilityScopes('admin'),
            ],
        );

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => 'Admin',
            'email' => 'admin-gate-'.uniqid().'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(false);
        config(['saas.default_tenant_slug' => $tenant->slug]);

        $response = $this->actingAs($user)->get('/mail');

        $response->assertForbidden();
    }

    public function test_mail_route_is_available_on_pro_plan(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'pro-gate-'.uniqid(),
            'name' => 'Pro Gate',
            'status' => 'active',
            'plan' => 'pro',
        ]);

        $role = Role::query()->firstOrCreate(
            ['name' => 'admin', 'tenant_id' => $tenant->id],
            [
                'display_name' => 'Admin',
                'permissions' => RoleAccess::permissionKeys(),
                'visibility_areas' => RoleAccess::defaultVisibilityAreas('admin'),
                'visibility_scopes' => RoleAccess::defaultVisibilityScopes('admin'),
            ],
        );

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => 'Admin Pro',
            'email' => 'pro-gate-'.uniqid().'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(false);
        config(['saas.default_tenant_slug' => $tenant->slug]);

        $response = $this->actingAs($user)->get('/mail');

        $response->assertOk();
    }
}
