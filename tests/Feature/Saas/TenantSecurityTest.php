<?php

namespace Tests\Feature\Saas;

use App\Models\Tenant;
use App\Models\User;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Tests\SaasTestCase;

class TenantSecurityTest extends SaasTestCase
{
    public function test_tenant_scope_is_fail_closed_without_context(): void
    {
        TenantContext::clear();

        $this->assertSame(0, User::query()->count());
    }

    public function test_login_rejects_user_from_another_tenant(): void
    {
        TenantContext::bypass(true);

        $tenantA = Tenant::query()->create([
            'slug' => 'sec-a-'.uniqid(),
            'name' => 'Security A',
            'status' => 'active',
            'plan' => 'start',
        ]);

        $tenantB = Tenant::query()->create([
            'slug' => 'sec-b-'.uniqid(),
            'name' => 'Security B',
            'status' => 'active',
            'plan' => 'start',
        ]);

        $roleId = \App\Models\Role::query()->firstOrCreate(
            ['name' => 'manager', 'tenant_id' => $tenantA->id],
            [
                'display_name' => 'Manager',
                'permissions' => RoleAccess::permissionKeys(),
                'visibility_areas' => RoleAccess::defaultVisibilityAreas('manager'),
                'visibility_scopes' => RoleAccess::defaultVisibilityScopes('manager'),
            ],
        )->id;

        User::query()->create([
            'tenant_id' => $tenantB->id,
            'role_id' => $roleId,
            'name' => 'Other Tenant User',
            'email' => 'other-'.uniqid().'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(true);
        $otherEmail = User::query()->where('tenant_id', $tenantB->id)->value('email');
        TenantContext::bypass(false);

        config(['saas.default_tenant_slug' => $tenantA->slug]);

        $response = $this->post('/login', [
            'email' => $otherEmail,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_tenant_feature_enabled_respects_plan(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'plan-'.uniqid(),
            'name' => 'Plan Test',
            'status' => 'active',
            'plan' => 'start',
        ]);

        TenantContext::clear();

        $this->assertTrue($tenant->featureEnabled('leads'));
        $this->assertFalse($tenant->featureEnabled('mail'));
    }
}
