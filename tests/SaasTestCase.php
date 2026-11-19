<?php

namespace Tests;

use App\Support\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

abstract class SaasTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
    }

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

        $this->app->make(Kernel::class)->setArtisan(null);
    }
}
