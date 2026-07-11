<?php

use App\Models\Tenant;
use App\Services\Saas\TenantProvisioner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_subscriptions') || ! Schema::hasTable('tenants')) {
            return;
        }

        $provisioner = app(TenantProvisioner::class);

        Tenant::query()->each(function (Tenant $tenant) use ($provisioner): void {
            $provisioner->syncSubscription($tenant);
        });
    }

    public function down(): void
    {
        // Data backfill — no rollback.
    }
};
