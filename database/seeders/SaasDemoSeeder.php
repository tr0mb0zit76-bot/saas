<?php

namespace Database\Seeders;

use App\Models\Contractor;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Demo data for SaaS lab (minimal: users + 2 contractors + leads).
 *
 *   php artisan db:seed --class=SaasDemoSeeder
 *
 * Login: admin@saas.local / password  |  manager@saas.local / password
 */
class SaasDemoSeeder extends Seeder
{
    private const ADMIN_EMAIL = 'admin@saas.local';

    private const MANAGER_EMAIL = 'manager@saas.local';

    private const PASSWORD = 'password';

    public function run(): void
    {
        if (! Schema::hasTable('users')) {
            $this->command?->error('Run migrations first: php artisan migrate');

            return;
        }

        if (User::query()->where('email', self::ADMIN_EMAIL)->exists()) {
            $this->command?->warn('SaasDemoSeeder: demo users already exist, skipping.');

            return;
        }

        $this->call(DatabaseSeeder::class);

        DB::transaction(function (): void {
            $roles = $this->seedRoles();
            [$admin, $manager] = $this->seedUsers($roles);
            $this->seedOwnCompany($admin);
            $this->seedContractors($admin, $manager);
            $this->seedLeads($manager);
        });

        $this->command?->info('SaasDemoSeeder OK');
        $this->command?->info('Login: '.self::ADMIN_EMAIL.' / '.self::MANAGER_EMAIL.' — password: '.self::PASSWORD);
        $this->command?->info('Contractors: '.Contractor::query()->count().' | Leads: '.Lead::query()->count());
    }

    /**
     * @return array{admin: Role, manager: Role}
     */
    private function seedRoles(): array
    {
        $make = fn (string $name, string $displayName) => Role::query()->updateOrCreate(
            ['name' => $name],
            [
                'display_name' => $displayName,
                'description' => 'SaaS demo role',
                'permissions' => RoleAccess::permissionKeys(),
                'visibility_areas' => RoleAccess::defaultVisibilityAreas($name),
                'visibility_scopes' => RoleAccess::defaultVisibilityScopes($name),
            ],
        );

        return [
            'admin' => $make('admin', 'Администратор'),
            'manager' => $make('manager', 'Менеджер'),
        ];
    }

    /**
     * @param  array{admin: Role, manager: Role}  $roles
     * @return array{0: User, 1: User}
     */
    private function seedUsers(array $roles): array
    {
        $hash = Hash::make(self::PASSWORD);

        $admin = User::query()->create([
            'role_id' => $roles['admin']->id,
            'name' => 'Demo Admin',
            'email' => self::ADMIN_EMAIL,
            'password' => $hash,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $manager = User::query()->create([
            'role_id' => $roles['manager']->id,
            'name' => 'Demo Manager',
            'email' => self::MANAGER_EMAIL,
            'password' => $hash,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        return [$admin, $manager];
    }

    private function seedOwnCompany(User $admin): void
    {
        if (! Schema::hasColumn('contractors', 'is_own_company')) {
            return;
        }

        Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО «Демо Экспедиция»',
            'full_name' => 'ООО «Демо Экспедиция»',
            'inn' => '7700000001',
            'is_own_company' => true,
            'is_active' => true,
            'is_verified' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }

    private function seedContractors(User $admin, User $manager): void
    {
        $base = [
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ];

        if (Schema::hasColumn('contractors', 'owner_id')) {
            $base['owner_id'] = $manager->id;
        }

        Contractor::query()->create(array_merge($base, [
            'type' => 'customer',
            'name' => 'ООО «Тест Заказчик»',
            'inn' => '7701234567',
            'phone' => '+7 (495) 100-00-01',
            'email' => 'customer@demo.saas',
        ]));

        Contractor::query()->create(array_merge($base, [
            'type' => 'carrier',
            'name' => 'ИП «Тест Перевозчик»',
            'inn' => '780198765432',
            'phone' => '+7 (812) 200-00-02',
            'email' => 'carrier@demo.saas',
        ]));
    }

    private function seedLeads(User $manager): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        Lead::factory()->count(2)->create([
            'responsible_id' => $manager->id,
            'loading_location' => 'Москва',
            'unloading_location' => 'Санкт-Петербург',
        ]);
    }
}
