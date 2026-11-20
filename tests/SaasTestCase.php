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
        // Do not pass --schema-path=.skip-mysql-cli-load: an empty dump makes
        // migrate:fresh drop the migrations table after "schema load" (Windows/MySQL).
        // Full migration replay is ~30–60s and is the reliable path for Feature/Saas.
        return [];
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
