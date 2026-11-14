<?php

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Migrations\Migration;

/**
 * ADR-019 pilot: Orientum must not see Lead «Предрасчёт» customs blocks.
 * Prod Orientum was flipped to enterprise + full feature catalog (incl. import_cost);
 * Start/Pro plans must never carry import_cost either.
 */
return new class extends Migration
{
    public function up(): void
    {
        TenantContext::bypass(true);

        foreach (['start', 'pro'] as $planKey) {
            $plan = SubscriptionPlan::query()->where('key', $planKey)->first();
            if ($plan === null) {
                continue;
            }

            $features = array_values(array_filter(
                $plan->featuresList(),
                static fn (string $feature): bool => $feature !== 'import_cost',
            ));

            if ($features !== $plan->featuresList()) {
                $plan->update(['features' => $features]);
            }
        }

        $tenant = Tenant::query()->where('slug', 'orientum')->first();
        if ($tenant === null) {
            TenantContext::bypass(false);

            return;
        }

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $features = $settings['features'] ?? null;

        if (is_array($features) && array_is_list($features)) {
            $settings['features'] = array_values(array_filter(
                $features,
                static fn (mixed $feature): bool => $feature !== 'import_cost',
            ));
        } elseif (is_array($features)) {
            $features['import_cost'] = false;
            $settings['features'] = $features;
        } else {
            // No overrides: enterprise plan still includes import_cost — force off for pilot.
            $settings['features'] = ['import_cost' => false];
        }

        $tenant->update(['settings' => $settings]);

        TenantContext::bypass(false);
    }

    public function down(): void
    {
        // Intentionally empty: do not re-enable customs for the Orientum pilot.
    }
};
