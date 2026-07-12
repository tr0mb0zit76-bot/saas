<?php

namespace App\Services\Saas;

use App\Models\Tenant;
use App\Services\Saas\TenantOnboardingService;
use App\Support\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DemoSignupService
{
    public function __construct(
        private readonly TenantProvisioner $provisioner,
        private readonly TenantOnboardingService $onboarding,
    ) {}

    /**
     * @return array{tenant: Tenant, user: \App\Models\User, password: string}
     */
    public function register(string $companyName, string $adminName, string $adminEmail): array
    {
        if (! config('saas.demo_signup_enabled', false)) {
            throw ValidationException::withMessages([
                'company_name' => 'Регистрация демо-доступа временно недоступна.',
            ]);
        }

        TenantContext::bypass(true);

        $slug = $this->uniqueSlug($companyName);

        $tenant = Tenant::query()->create([
            'slug' => $slug,
            'name' => $companyName,
            'status' => 'trial',
            'plan' => 'start',
            'trial_ends_at' => now()->addDays((int) config('saas.trial_days', 14)),
            'settings' => [
                'demo_tenant' => true,
                'branding' => [
                    'product_name' => $companyName,
                ],
                'onboarding' => [],
            ],
        ]);

        $this->provisioner->provision($tenant);

        $onboarded = $this->onboarding->createAdminUser($tenant, $adminName, $adminEmail);
        $this->onboarding->sendWelcomeInvite($tenant, $onboarded['user'], $onboarded['password']);

        TenantContext::bypass(false);

        return [
            'tenant' => $tenant->fresh(),
            'user' => $onboarded['user'],
            'password' => $onboarded['password'],
        ];
    }

    private function uniqueSlug(string $companyName): string
    {
        $base = Str::slug($companyName);

        if ($base === '') {
            $base = 'demo';
        }

        $base = Str::limit($base, 40, '');

        $slug = $base;
        $suffix = 1;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        if (in_array($slug, ['platform', 'admin', 'www', 'api', 'mail'], true)) {
            $slug = $slug.'-co';
        }

        return $slug;
    }
}
