<?php

namespace Tests\Feature\Saas;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\SaasTestCase;

/**
 * Orientum-style tenant admin must open CRM «Пользователи» (settings.users.index).
 * Guards RBAC + TenantScope wiring; 503s seen in prod access logs were deploy maintenance.
 */
class TenantUsersPageTest extends SaasTestCase
{
    public function test_tenant_admin_can_open_users_page(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'users-page-'.uniqid(),
            'name' => 'Users Page Tenant',
            'status' => 'active',
            'plan' => 'enterprise',
            'settings' => ['onboarding' => ['completed_at' => now()->toIso8601String()]],
        ]);

        $adminRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'admin',
            'display_name' => 'Администратор',
            'permissions' => RoleAccess::permissionKeys(),
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('admin'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('admin'),
            ...(Schema::hasColumn('roles', 'has_signing_authority')
                ? ['has_signing_authority' => true]
                : []),
        ]);

        $managerRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'manager',
            'display_name' => 'Менеджер',
            'permissions' => [],
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('manager'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('manager'),
            ...(Schema::hasColumn('roles', 'has_signing_authority')
                ? ['has_signing_authority' => false]
                : []),
        ]);

        TenantContext::bypass(false);
        TenantContext::set($tenant);
        config(['saas.default_tenant_slug' => $tenant->slug]);

        $admin = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $adminRole->id,
            'name' => 'Tenant Admin',
            'email' => 'admin-'.$tenant->slug.'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $managerRole->id,
            'name' => 'Tenant Manager',
            'email' => 'manager-'.$tenant->slug.'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('settings.users.index'));

        $response->assertOk();
        $response->assertInertia(function (Assert $page): Assert {
            $page->component('Users/Index')
                ->has('users', 2)
                ->has('roles', 2);

            if (Schema::hasColumn('roles', 'has_signing_authority')) {
                // orderBy display_name: Администратор before Менеджер
                $page->where('roles.0.default_has_signing_authority', true)
                    ->where('roles.1.default_has_signing_authority', false);
            }

            return $page;
        });
    }

    public function test_tenant_manager_without_settings_cannot_open_users_page(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'users-denied-'.uniqid(),
            'name' => 'Users Denied Tenant',
            'status' => 'active',
            'plan' => 'start',
            'settings' => ['onboarding' => ['completed_at' => now()->toIso8601String()]],
        ]);

        $managerRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'manager',
            'display_name' => 'Менеджер',
            'permissions' => [],
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('manager'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('manager'),
        ]);

        TenantContext::bypass(false);
        TenantContext::set($tenant);
        config(['saas.default_tenant_slug' => $tenant->slug]);

        $manager = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $managerRole->id,
            'name' => 'Manager',
            'email' => 'manager-'.$tenant->slug.'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('settings.users.index'))
            ->assertForbidden();
    }
}
