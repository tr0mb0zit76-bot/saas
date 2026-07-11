<?php

namespace Database\Seeders;

use App\Models\Contractor;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Saas\TenantProvisioner;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds demo-a and demo-b tenants for isolation testing (M5).
 *
 *   php artisan db:seed --class=TenantDemoSeeder
 */
class TenantDemoSeeder extends Seeder
{
    public function run(): void
    {
        TenantContext::bypass(true);

        $provisioner = app(TenantProvisioner::class);

        foreach (['demo-a' => 'Demo Alpha Logistics', 'demo-b' => 'Demo Beta Transport'] as $slug => $name) {
            $tenant = Tenant::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'status' => 'active', 'plan' => 'start', 'settings' => ['features' => ['leads', 'orders', 'contractors']]],
            );

            $roles = $provisioner->seedRoles($tenant);
            $provisioner->syncSubscription($tenant);
            $role = $roles['manager'];

            $email = "manager@{$slug}.saas.local";
            $userPayload = [
                'tenant_id' => $tenant->id,
                'role_id' => $role->id,
                'name' => "Manager {$slug}",
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ];

            $manager = User::query()->updateOrCreate(['email' => $email, 'tenant_id' => $tenant->id], $userPayload);

            Contractor::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'inn' => "7700000{$tenant->id}01"],
                [
                    'type' => 'customer',
                    'name' => "Заказчик {$slug}",
                    'is_active' => true,
                    'created_by' => $manager->id,
                    'updated_by' => $manager->id,
                ],
            );

            Contractor::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'inn' => "7700000{$tenant->id}02"],
                [
                    'type' => 'carrier',
                    'name' => "Перевозчик {$slug}",
                    'is_active' => true,
                    'created_by' => $manager->id,
                    'updated_by' => $manager->id,
                ],
            );
        }

        TenantContext::bypass(false);
        $this->command?->info('TenantDemoSeeder: demo-a, demo-b ready');
    }
}
