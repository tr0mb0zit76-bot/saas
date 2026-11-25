<?php

use App\Models\Tenant;
use App\Services\ContentPacks\SalesBookContentPackInstaller;
use App\Services\Saas\TenantConnectorService;
use App\Support\TenantContext;
use Illuminate\Database\Migrations\Migration;

/**
 * ADR-021 class A + ADR-022 Orientum cutover:
 * print verification on, full sales-book packs, clone AA 1C connector into Orientum settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->where('slug', 'orientum')->first();
        if ($tenant === null) {
            TenantContext::bypass(false);

            return;
        }

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        data_set($settings, 'print.document_verification', true);
        $tenant->forceFill(['settings' => $settings])->save();
        $tenant->refresh();

        app(SalesBookContentPackInstaller::class)->installMany(
            $tenant,
            SalesBookContentPackInstaller::classAPacks(),
        );

        $tenant->refresh();

        if ($tenant->featureEnabled('integrations')) {
            try {
                app(TenantConnectorService::class)->cloneFromTemplate($tenant, 'aa_one_c_bp');
            } catch (\Throwable) {
                // Template/env may be incomplete in lab — packs + verification still applied.
            }
        }

        TenantContext::bypass(false);
    }

    public function down(): void
    {
        // Intentionally empty: do not strip Orientum packs/connector on rollback.
    }
};
