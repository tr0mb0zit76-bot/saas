<?php

namespace Tests\Feature\Saas;

use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantAuditLog;
use App\Models\User;
use App\Services\Finance\PaymentSchedulePaymentLedgerService;
use App\Services\OrderStatusService;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Tests\SaasTestCase;

class TenantCrmAuditTest extends SaasTestCase
{
    public function test_order_status_change_writes_audit_log(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'crm-audit-'.uniqid(),
            'name' => 'CRM Audit Co',
            'status' => 'active',
            'plan' => 'pro',
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

        $admin = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => 'Admin',
            'email' => 'audit-admin-'.uniqid().'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        TenantContext::bypass(false);
        TenantContext::set($tenant);

        $order = Order::query()->create([
            'tenant_id' => $tenant->id,
            'order_number' => 'AUD-001',
            'company_code' => 'AUD',
            'manager_id' => $admin->id,
            'status' => 'new',
            'manual_status' => 'cancelled',
            'is_active' => true,
        ]);

        app(OrderStatusService::class)->syncStoredStatus($order, $admin->id);

        $this->assertDatabaseHas('tenant_audit_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'action' => 'order.status_changed',
            'entity_type' => 'order',
            'entity_id' => $order->id,
        ]);

        $log = TenantAuditLog::query()->where('action', 'order.status_changed')->first();
        $this->assertSame('new', $log->old_values['status'] ?? null);
        $this->assertSame('cancelled', $log->new_values['status'] ?? null);
    }

    public function test_role_update_writes_audit_log(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'role-audit-'.uniqid(),
            'name' => 'Role Audit Co',
            'status' => 'active',
            'plan' => 'start',
            'settings' => ['onboarding' => ['completed_at' => now()->toIso8601String()]],
        ]);

        $adminRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'admin',
            'display_name' => 'Admin',
            'permissions' => RoleAccess::permissionKeys(),
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('admin'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('admin'),
        ]);

        $admin = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $adminRole->id,
            'name' => 'Admin',
            'email' => 'role-admin-'.uniqid().'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $customRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'viewer',
            'display_name' => 'Viewer',
            'permissions' => ['view_orders'],
            'visibility_areas' => ['dashboard', 'orders'],
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('manager'),
        ]);

        TenantContext::bypass(false);
        TenantContext::set($tenant);
        config(['saas.default_tenant_slug' => $tenant->slug]);

        $this->actingAs($admin)->patch(route('roles.update', $customRole), [
            'name' => 'viewer',
            'display_name' => 'Viewer Updated',
            'description' => null,
            'permissions' => ['view_orders', 'view_reports'],
            'visibility_areas' => ['dashboard', 'orders', 'reports'],
            'visibility_scopes' => [
                'dashboard' => ['mode' => 'all'],
                'orders' => ['mode' => 'own'],
                'reports' => ['mode' => 'all'],
            ],
            'has_signing_authority' => false,
        ])->assertRedirect(route('settings.roles.index'));

        $this->assertDatabaseHas('tenant_audit_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'action' => 'role.updated',
            'entity_type' => 'role',
            'entity_id' => $customRole->id,
        ]);
    }

    public function test_payment_recorded_writes_audit_log(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'pay-audit-'.uniqid(),
            'name' => 'Pay Audit Co',
            'status' => 'active',
            'plan' => 'pro',
        ]);

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'admin',
            'display_name' => 'Admin',
            'permissions' => RoleAccess::permissionKeys(),
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('admin'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('admin'),
        ]);

        $admin = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => 'Admin',
            'email' => 'pay-admin-'.uniqid().'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'tenant_id' => $tenant->id,
            'order_number' => 'PAY-001',
            'company_code' => 'PAY',
            'manager_id' => $admin->id,
            'status' => 'payment',
            'is_active' => true,
        ]);

        $schedule = PaymentSchedule::query()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'party' => 'customer',
            'amount' => 10000,
            'due_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        TenantContext::bypass(false);
        TenantContext::set($tenant);

        $event = app(PaymentSchedulePaymentLedgerService::class)->recordFromPaymentSchedule(
            $schedule,
            5000.00,
            now()->toDateString(),
            ['payment_method' => 'bank_transfer'],
            $admin->id,
        );

        $this->assertNotNull($event);

        $this->assertDatabaseHas('tenant_audit_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'action' => 'payment.recorded',
            'entity_type' => 'payment_schedule_payment_event',
            'entity_id' => $event->id,
        ]);
    }

    public function test_payment_reversal_writes_audit_log(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'rev-audit-'.uniqid(),
            'name' => 'Reversal Audit Co',
            'status' => 'active',
            'plan' => 'pro',
        ]);

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'admin',
            'display_name' => 'Admin',
            'permissions' => RoleAccess::permissionKeys(),
            'visibility_areas' => RoleAccess::defaultVisibilityAreas('admin'),
            'visibility_scopes' => RoleAccess::defaultVisibilityScopes('admin'),
        ]);

        $admin = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => 'Admin',
            'email' => 'rev-admin-'.uniqid().'@saas.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'tenant_id' => $tenant->id,
            'order_number' => 'REV-001',
            'company_code' => 'REV',
            'manager_id' => $admin->id,
            'status' => 'payment',
            'is_active' => true,
        ]);

        $schedule = PaymentSchedule::query()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'party' => 'customer',
            'amount' => 10000,
            'due_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        TenantContext::bypass(false);
        TenantContext::set($tenant);

        $event = app(PaymentSchedulePaymentLedgerService::class)->recordFromPaymentSchedule(
            $schedule,
            5000.00,
            now()->toDateString(),
            [],
            $admin->id,
        );

        app(\App\Services\Finance\PaymentSchedulePaymentReversalService::class)->reverseEvent(
            $event,
            $admin,
            'test reversal',
        );

        $this->assertDatabaseHas('tenant_audit_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'action' => 'payment.reversed',
            'entity_type' => 'payment_schedule_payment_event',
            'entity_id' => $event->id,
        ]);
    }
}
