<?php

namespace Tests\Feature\Saas;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Tests\SaasTestCase;

class TenantReadOnlyTest extends SaasTestCase
{
    public function test_suspended_tenant_can_view_but_not_create_lead(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'readonly-'.uniqid(),
            'name' => 'Read Only Co',
            'status' => 'suspended',
            'plan' => 'start',
            'settings' => ['onboarding' => ['completed_at' => now()->toIso8601String()]],
        ]);

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'admin',
            'display_name' => 'Admin',
            'permissions' => RoleAccess::permissionKeys(),
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('admin'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('admin'),
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => 'Admin',
            'email' => 'readonly-'.uniqid().'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(false);
        config(['saas.default_tenant_slug' => $tenant->slug]);
        TenantContext::set($tenant);

        $this->actingAs($user)->get('/leads')->assertOk();
        $this->actingAs($user)->post('/leads', [
            'title' => 'Blocked lead',
        ])->assertForbidden();
    }
}
