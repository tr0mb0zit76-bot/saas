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
            SubscriptionPlan::query()->updateOrCreate(
                ['key' => (string) $key],
                [
                    'label' => (string) ($plan['label'] ?? $key),
                    'features' => array_values((array) ($plan['features'] ?? [])),
                    'limits' => (array) ($plan['limits'] ?? []),
                    'is_active' => true,
                    'sort_order' => $sort++,
                ],
            );
        }

        $this->command?->info('SubscriptionPlanSeeder OK');
    }
}
