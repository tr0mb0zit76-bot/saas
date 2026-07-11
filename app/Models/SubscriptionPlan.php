<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SubscriptionPlan extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'key',
        'label',
        'features',
        'limits',
        'is_active',
        'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'limits' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public static function findByKey(string $key): ?self
    {
        $key = strtolower(trim($key));

        if ($key === '') {
            return null;
        }

        if (! Schema::hasTable('subscription_plans')) {
            return null;
        }

        return self::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return list<string>
     */
    public function featuresList(): array
    {
        /** @var list<string> $features */
        $features = is_array($this->features) ? $this->features : [];

        return array_values($features);
    }

    /**
     * @return array<string, int|null>
     */
    public function limitsMap(): array
    {
        /** @var array<string, int|null> $limits */
        $limits = is_array($this->limits) ? $this->limits : [];

        return $limits;
    }

    /**
     * @return list<array{key: string, label: string, features: list<string>, limits: array<string, int|null>}>
     */
    public static function summaries(): array
    {
        if (! Schema::hasTable('subscription_plans')) {
            return self::summariesFromConfig();
        }

        $plans = self::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('key')
            ->get();

        if ($plans->isEmpty()) {
            return self::summariesFromConfig();
        }

        return $plans
            ->map(fn (self $plan): array => [
                'key' => $plan->key,
                'label' => $plan->label,
                'features' => $plan->featuresList(),
                'limits' => $plan->limitsMap(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function selectOptions(): array
    {
        return collect(self::summaries())
            ->map(fn (array $plan): array => [
                'value' => $plan['key'],
                'label' => $plan['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function planKeys(): array
    {
        return collect(self::summaries())->pluck('key')->values()->all();
    }

    /**
     * @return list<array{key: string, label: string, features: list<string>, limits: array<string, int|null>}>
     */
    private static function summariesFromConfig(): array
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
