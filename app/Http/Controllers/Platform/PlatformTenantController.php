<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StorePlatformTenantRequest;
use App\Http\Requests\Platform\UpdatePlatformTenantRequest;
use App\Models\Tenant;
use App\Support\TenantContext;
use App\Support\TenantStorage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlatformTenantController extends Controller
{
    public function index(): Response
    {
        TenantContext::bypass(true);

        $tenants = Tenant::query()
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'status' => $tenant->status,
                'plan' => $tenant->planKey(),
                'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
                'users_count' => $tenant->users_count,
                'features' => $tenant->enabledFeatures(),
                'created_at' => $tenant->created_at?->toIso8601String(),
            ])
            ->values();

        TenantContext::bypass(false);

        return Inertia::render('Platform/Tenants/Index', [
            'tenants' => $tenants,
            'planOptions' => collect(config('saas-plans.plans', []))
                ->map(fn (array $plan, string $key): array => [
                    'value' => $key,
                    'label' => (string) ($plan['label'] ?? $key),
                ])
                ->values(),
            'statusOptions' => [
                ['value' => 'active', 'label' => 'Активен'],
                ['value' => 'trial', 'label' => 'Пробный период'],
                ['value' => 'suspended', 'label' => 'Приостановлен'],
            ],
        ]);
    }

    public function store(StorePlatformTenantRequest $request): RedirectResponse
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => $request->string('slug')->toString(),
            'name' => $request->string('name')->toString(),
            'status' => $request->string('status')->toString(),
            'plan' => $request->string('plan')->toString(),
            'trial_ends_at' => $request->date('trial_ends_at'),
            'settings' => [
                'branding' => [
                    'product_name' => $request->string('name')->toString(),
                ],
            ],
        ]);

        TenantStorage::provisionFor($tenant);

        TenantContext::bypass(false);

        return to_route('platform.tenants.index')->with('flash', [
            'type' => 'success',
            'message' => "Арендатор «{$tenant->name}» создан.",
        ]);
    }

    public function update(UpdatePlatformTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        TenantContext::bypass(true);

        $tenant->update([
            'name' => $request->string('name')->toString(),
            'status' => $request->string('status')->toString(),
            'plan' => $request->string('plan')->toString(),
            'trial_ends_at' => $request->date('trial_ends_at'),
        ]);

        TenantContext::bypass(false);

        return to_route('platform.tenants.index')->with('flash', [
            'type' => 'success',
            'message' => "Арендатор «{$tenant->name}» обновлён.",
        ]);
    }
}
