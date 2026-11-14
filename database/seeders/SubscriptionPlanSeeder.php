<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $sort = 0;

        foreach (config('saas-plans.plans', []) as $key => $plan) {
            $key = (string) $key;
            $existing = SubscriptionPlan::query()->where('key', $key)->first();

            // Preserve platform-admin checkbox edits in DB; only seed features/limits on create.
            if ($existing !== null) {
                $existing->update([
                    'label' => (string) ($plan['label'] ?? $key),
                    'is_active' => true,
                    'sort_order' => $sort++,
                ]);

                continue;
            }

            SubscriptionPlan::query()->create([
                'key' => $key,
                'label' => (string) ($plan['label'] ?? $key),
                'features' => array_values((array) ($plan['features'] ?? [])),
                'limits' => (array) ($plan['limits'] ?? []),
                'is_active' => true,
                'sort_order' => $sort++,
            ]);
        }

        $this->command?->info('SubscriptionPlanSeeder OK');
    }
}
