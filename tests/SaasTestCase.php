<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

abstract class SaasTestCase extends TestCase
{
    use RefreshDatabase {
        refreshTestDatabase as baseRefreshTestDatabase;
    }

    /**
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing(): array
    {
        return [
            '--schema-path' => database_path('schema/.skip-mysql-cli-load'),
        ];
    }

    protected function refreshTestDatabase(): void
    {
        RefreshDatabaseState::$migrated = false;

        $this->artisan('migrate:fresh', array_merge(
            [
                '--drop-views' => true,
                '--force' => true,
            ],
            $this->migrateFreshUsing(),
        ));

        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->setArtisan(null);
    }
}
