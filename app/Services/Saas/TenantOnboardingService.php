<?php

namespace App\Services\Saas;

use App\Mail\TenantWelcomeMail;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class TenantOnboardingService
{
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
    }
}
