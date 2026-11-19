<?php

namespace App\Support;

use App\Models\SubscriptionPlan;

final class SaasFeatureCatalog
{
    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(config('saas-features.catalog', []));
    }

    /**
     * @return array<string, array{label: string, group: string}>
     */
    public static function catalog(): array
    {
        /** @var array<string, array{label: string, group: string}> */
        return config('saas-features.catalog', []);
    }

    /**
     * @return array<string, string>
     */
    public static function groups(): array
    {
        /** @var array<string, string> */
        return config('saas-features.groups', []);
    }

    /**
     * @return list<array{key: string, label: string, group: string, group_label: string}>
     */
    public static function groupedFeatures(): array
    {
        $groups = self::groups();
        $items = [];

        foreach (self::catalog() as $key => $meta) {
            $group = (string) ($meta['group'] ?? 'core');
            $items[] = [
                'key' => $key,
                'label' => (string) ($meta['label'] ?? $key),
                'group' => $group,
                'group_label' => (string) ($groups[$group] ?? $group),
            ];
        }

        return $items;
    }

    /**
     * @return list<array{key: string, label: string, features: list<string>}>
     */
    public static function planSummaries(): array
    {
        return SubscriptionPlan::summaries();
    }

    /**
     * Матрица модулей × тарифы — тот же источник, что /platform/plans.
     *
     * @return list<array{
     *     key: string,
     *     label: string,
     *     group: string,
     *     group_label: string,
     *     plans: array<string, bool>
     * }>
     */
    public static function planMatrix(): array
    {
        $plans = self::planSummaries();

        return array_map(function (array $feature) use ($plans): array {
            $row = [
                'key' => $feature['key'],
                'label' => $feature['label'],
                'group' => $feature['group'],
                'group_label' => $feature['group_label'],
                'plans' => [],
            ];

            foreach ($plans as $plan) {
                $row['plans'][$plan['key']] = in_array($feature['key'], $plan['features'], true);
            }

            return $row;
        }, self::groupedFeatures());
    }
}
