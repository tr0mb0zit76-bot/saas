<?php

namespace App\Support;

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
        $summaries = [];

        foreach (config('saas-plans.plans', []) as $planKey => $plan) {
            $summaries[] = [
                'key' => (string) $planKey,
                'label' => (string) ($plan['label'] ?? $planKey),
                'features' => array_values((array) ($plan['features'] ?? [])),
                'limits' => (array) ($plan['limits'] ?? []),
            ];
        }

        return $summaries;
    }
}
