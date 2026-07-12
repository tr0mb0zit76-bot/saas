<?php

namespace App\Services\Saas;

use App\Models\Contractor;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Schema;

final class TenantOnboardingWizardService
{
    /**
     * @param  array{company_name: string, inn?: ?string, timezone: string, sample_customer_name?: ?string}  $data
     */
    public function complete(Tenant $tenant, User $user, array $data): Tenant
    {
        return TenantContext::runAs($tenant, function () use ($tenant, $user, $data): Tenant {
            if (Schema::hasColumn('contractors', 'is_own_company')) {
                Contractor::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'is_own_company' => true,
                    ],
                    [
                        'type' => 'customer',
                        'name' => $data['company_name'],
                        'full_name' => $data['company_name'],
                        'inn' => $data['inn'] ?? null,
                        'is_active' => true,
                        'is_verified' => true,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ],
                );
            }

            $sampleName = trim((string) ($data['sample_customer_name'] ?? ''));

            if ($sampleName !== '' && Schema::hasTable('contractors')) {
                Contractor::query()->create([
                    'tenant_id' => $tenant->id,
                    'type' => 'customer',
                    'name' => $sampleName,
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            $settings = is_array($tenant->settings) ? $tenant->settings : [];
            $settings['onboarding'] = [
                'completed_at' => now()->toIso8601String(),
                'timezone' => $data['timezone'],
            ];

            $tenant->update(['settings' => $settings]);

            return $tenant->fresh();
        });
    }
}
