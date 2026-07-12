<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'slug',
        'name',
        'status',
        'plan',
        'settings',
        'trial_ends_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function contractors(): HasMany
    {
        return $this->hasMany(Contractor::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasOne<TenantSubscription, $this> */
    public function subscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class);
    }

    /** @return HasMany<TenantInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(TenantInvoice::class);
    }

    /** @return HasMany<TenantUsageLog, $this> */
    public function usageLogs(): HasMany
    {
        return $this->hasMany(TenantUsageLog::class);
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isReadOnly(): bool
    {
        return $this->isSuspended();
    }

    public function isDemoTenant(): bool
    {
        return (bool) data_get($this->settings, 'demo_tenant', false);
    }

    public function onboardingCompleted(): bool
    {
        if (data_get($this->settings, 'onboarding.completed_at') !== null) {
            return true;
        }

        return $this->ownCompanyContractor() !== null;
    }

    public function ownCompanyContractor(): ?Contractor
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('contractors', 'is_own_company')) {
            return null;
        }

        return $this->contractors()->where('is_own_company', true)->first();
    }

    public function planKey(): string
    {
        $plan = strtolower(trim((string) ($this->plan ?: 'start')));

        $known = collect(SubscriptionPlan::summaries())
            ->pluck('key')
            ->all();

        if ($known !== [] && in_array($plan, $known, true)) {
            return $plan;
        }

        return array_key_exists($plan, config('saas-plans.plans', [])) ? $plan : 'start';
    }

    /**
     * @return list<string>
     */
    public function planFeatures(): array
    {
        $subscriptionPlan = SubscriptionPlan::findByKey($this->planKey());

        if ($subscriptionPlan !== null) {
            return $subscriptionPlan->featuresList();
        }

        /** @var list<string> $features */
        $features = config('saas-plans.plans.'.$this->planKey().'.features', []);

        return $features;
    }

    /**
     * @return array<string, int|null>
     */
    public function planLimits(): array
    {
        $subscriptionPlan = SubscriptionPlan::findByKey($this->planKey());

        if ($subscriptionPlan !== null) {
            return $subscriptionPlan->limitsMap();
        }

        /** @var array<string, int|null> $limits */
        $limits = config('saas-plans.plans.'.$this->planKey().'.limits', []);

        return $limits;
    }

    public function featureEnabled(string $key): bool
    {
        $overrides = data_get($this->settings, 'features');

        if (is_array($overrides)) {
            if (array_is_list($overrides)) {
                return in_array($key, $overrides, true);
            }

            if (array_key_exists($key, $overrides)) {
                return (bool) $overrides[$key];
            }
        }

        return in_array($key, $this->planFeatures(), true);
    }

    /**
     * @return list<string>
     */
    public function enabledFeatures(): array
    {
        $keys = $this->planFeatures();
        $overrides = data_get($this->settings, 'features');

        if (is_array($overrides) && array_is_list($overrides)) {
            $keys = array_values(array_unique([...$keys, ...$overrides]));
        } elseif (is_array($overrides)) {
            $keys = array_values(array_unique([...$keys, ...array_keys($overrides)]));
        }

        return array_values(array_filter(
            $keys,
            fn (string $feature): bool => $this->featureEnabled($feature),
        ));
    }

    /**
     * @return array{product_name: string, mobile_app_name: string, primary_accent: string, logo_path: ?string}
     */
    public function branding(): array
    {
        $defaults = [
            'product_name' => (string) config('app.name', 'Traklo Pro'),
            'mobile_app_name' => (string) config('saas.mobile_app_name', 'Traklo Pro'),
            'primary_accent' => 'sky',
            'logo_path' => null,
        ];

        $branding = data_get($this->settings, 'branding');

        if (! is_array($branding)) {
            return $defaults;
        }

        return [
            'product_name' => (string) ($branding['product_name'] ?? $defaults['product_name']),
            'mobile_app_name' => (string) ($branding['mobile_app_name'] ?? $defaults['mobile_app_name']),
            'primary_accent' => (string) ($branding['primary_accent'] ?? $defaults['primary_accent']),
            'logo_path' => isset($branding['logo_path']) ? (string) $branding['logo_path'] : null,
        ];
    }
}
