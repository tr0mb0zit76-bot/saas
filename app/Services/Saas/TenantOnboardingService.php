<?php

namespace App\Services\Saas;

use App\Mail\TenantWelcomeMail;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Saas\TenantAuditLogger;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class TenantOnboardingService
{
    public function __construct(
        private readonly TenantAuditLogger $auditLogger,
    ) {}

    /**
     * @return array{user: User, password: string}
     */
    public function createAdminUser(Tenant $tenant, string $name, string $email): array
    {
        $password = Str::password(16);

        $user = TenantContext::runAs($tenant, function () use ($tenant, $name, $email, $password): User {
            $adminRole = Role::query()
                ->where('tenant_id', $tenant->id)
                ->where('name', 'admin')
                ->first();

            abort_if($adminRole === null, 500, 'Admin role missing for tenant.');

            return User::query()->create([
                'tenant_id' => $tenant->id,
                'role_id' => $adminRole->id,
                'name' => $name,
                'email' => strtolower(trim($email)),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
        });

        return ['user' => $user, 'password' => $password];
    }

    public function sendWelcomeInvite(Tenant $tenant, User $user, string $password): void
    {
        Mail::to($user->email)->send(new TenantWelcomeMail(
            tenant: $tenant,
            user: $user,
            temporaryPassword: $password,
        ));

        $this->auditLogger->log(
            $tenant->id,
            null,
            'user.invited',
            'user',
            $user->id,
            null,
            ['email' => $user->email, 'tenant_slug' => $tenant->slug],
        );
    }

    /**
     * Reset temp password for the primary tenant admin and resend welcome mail.
     *
     * @return array{user: User, password: string}
     */
    public function resetAndResendWelcomeInvite(Tenant $tenant, ?User $admin = null): array
    {
        $user = $admin ?? $this->primaryAdmin($tenant);

        abort_if($user === null, 422, 'У арендатора нет активного администратора.');
        abort_unless((int) $user->tenant_id === (int) $tenant->id, 404);

        $password = Str::password(16);

        TenantContext::runAs($tenant, function () use ($user, $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'is_active' => true,
            ])->save();
        });

        $this->sendWelcomeInvite($tenant, $user->fresh(), $password);

        return ['user' => $user->fresh(), 'password' => $password];
    }

    public function primaryAdmin(Tenant $tenant): ?User
    {
        return TenantContext::runAs($tenant, function () use ($tenant): ?User {
            $adminRoleId = Role::query()
                ->where('tenant_id', $tenant->id)
                ->where('name', 'admin')
                ->value('id');

            if ($adminRoleId === null) {
                return null;
            }

            return User::query()
                ->where('tenant_id', $tenant->id)
                ->where('role_id', $adminRoleId)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();
        });
    }
}
