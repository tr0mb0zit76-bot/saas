<?php

namespace App\Services\Saas;

use App\Models\Tenant;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TenantErasureService
{
    /**
     * Delete one tenant's rows and files. Other tenants stay.
     */
    public function erase(Tenant $tenant): void
    {
        $tenantId = $tenant->id;
        $prefix = 'tenants/'.$tenantId;

        DB::transaction(function () use ($tenant, $tenantId): void {
            Schema::disableForeignKeyConstraints();

            try {
                foreach (Schema::getTables() as $table) {
                    $name = (string) ($table['name'] ?? '');

                    if ($name === 'tenants' || ! Schema::hasColumn($name, 'tenant_id')) {
                        continue;
                    }

                    DB::table($name)->where('tenant_id', $tenantId)->delete();
                }

                $tenant->delete();
            } finally {
                Schema::enableForeignKeyConstraints();
            }
        });

        if (TenantStorage::disk()->exists($prefix)) {
            TenantStorage::disk()->deleteDirectory($prefix);
        }
    }
}
