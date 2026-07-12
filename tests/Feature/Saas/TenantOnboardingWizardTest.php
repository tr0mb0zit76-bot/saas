<?php

namespace Tests\Feature\Saas;

use App\Models\Contractor;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Tests\SaasTestCase;

class TenantOnboardingWizardTest extends SaasTestCase
{
    public function test_new_tenant_is_redirected_to_onboarding(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'onboard-'.uniqid(),
            'name' => 'Onboard Co',
            'status' => 'trial',
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

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => 'Admin',
            'email' => 'onboard-'.uniqid().'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(false);
        config(['saas.default_tenant_slug' => $tenant->slug]);
        TenantContext::set($tenant);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('onboarding.show'));
    }

    public function test_onboarding_creates_own_company_and_skips_redirect(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'onboard-done-'.uniqid(),
            'name' => 'Done Co',
            'status' => 'trial',
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

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => 'Admin',
            'email' => 'onboard-done-'.uniqid().'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(false);
        config(['saas.default_tenant_slug' => $tenant->slug]);
        TenantContext::set($tenant);

        $this->actingAs($user)->post(route('onboarding.store'), [
            'company_name' => 'ООО «Done Co»',
            'inn' => '7700000099',
            'timezone' => 'Europe/Moscow',
            'sample_customer_name' => 'ООО «Клиент»',
        ])->assertRedirect(route('dashboard'));

        TenantContext::bypass(true);
        $this->assertTrue($tenant->fresh()->onboardingCompleted());
        $this->assertSame(1, Contractor::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_own_company', true)->count());
        TenantContext::bypass(false);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }
}
