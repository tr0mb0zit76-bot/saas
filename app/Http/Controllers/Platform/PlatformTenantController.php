<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StorePlatformTenantRequest;
use App\Http\Requests\Platform\UpdatePlatformTenantFeaturesRequest;
use App\Http\Requests\Platform\UpdatePlatformTenantRequest;
use App\Models\Tenant;
use App\Services\Saas\TenantBillingService;
use App\Services\Saas\TenantProvisioner;
use App\Support\SaasFeatureCatalog;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlatformTenantController extends Controller
{
    public function __construct(
        private readonly TenantProvisioner $provisioner,
        private readonly TenantBillingService $billing,
    ) {}

    public function index(): Response
    {
        TenantContext::bypass(true);

        $tenants = Tenant::query()
            ->with('subscription')
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
                'subscription_status' => $tenant->subscription?->status,
                'billing_period_end' => $tenant->subscription?->billing_period_end?->toDateString(),
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

        $this->provisioner->provision($tenant);

        TenantContext::bypass(false);

        return to_route('platform.tenants.index')->with('flash', [
            'type' => 'success',
            'message' => "Арендатор «{$tenant->name}» создан (роли и подписка подготовлены).",
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

        $this->provisioner->syncSubscription($tenant->fresh());

        TenantContext::bypass(false);

        return to_route('platform.tenants.index')->with('flash', [
            'type' => 'success',
            'message' => "Арендатор «{$tenant->name}» обновлён.",
        ]);
    }

    public function markPaid(Tenant $tenant): RedirectResponse
    {
        TenantContext::bypass(true);

        $this->billing->markInvoicePaid($tenant);

        TenantContext::bypass(false);

        return to_route('platform.tenants.index')->with('flash', [
            'type' => 'success',
            'message' => "Оплата за «{$tenant->name}» зафиксирована, период продлён.",
        ]);
    }

    public function features(Tenant $tenant): Response
    {
        TenantContext::bypass(true);

        $planFeatures = $tenant->planFeatures();
        $overrides = data_get($tenant->settings, 'features');
        $overrideMap = is_array($overrides) && ! array_is_list($overrides) ? $overrides : [];

        $features = array_map(function (array $item) use ($tenant, $planFeatures, $overrideMap): array {
            $key = $item['key'];
            $inPlan = in_array($key, $planFeatures, true);
            $hasOverride = array_key_exists($key, $overrideMap);

            return [
                ...$item,
                'in_plan' => $inPlan,
                'enabled' => $tenant->featureEnabled($key),
                'override' => $hasOverride ? (bool) $overrideMap[$key] : null,
            ];
        }, SaasFeatureCatalog::groupedFeatures());

        TenantContext::bypass(false);

        return Inertia::render('Platform/Tenants/Features', [
            'tenant' => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'plan' => $tenant->planKey(),
            ],
            'features' => $features,
        ]);
    }

    public function updateFeatures(UpdatePlatformTenantFeaturesRequest $request, Tenant $tenant): RedirectResponse
    {
        TenantContext::bypass(true);

        $planFeatures = $tenant->planFeatures();
        $overrides = [];

        foreach ($request->validated('features') as $key => $enabled) {
            $inPlan = in_array($key, $planFeatures, true);

            if ((bool) $enabled !== $inPlan) {
                $overrides[$key] = (bool) $enabled;
            }
        }

        $settings = is_array($tenant->settings) ? $tenant->settings : [];

        if ($overrides === []) {
            unset($settings['features']);
        } else {
            $settings['features'] = $overrides;
        }

        $tenant->update(['settings' => $settings]);

        TenantContext::bypass(false);

        return to_route('platform.tenants.features', $tenant)->with('flash', [
            'type' => 'success',
            'message' => "Модули арендатора «{$tenant->name}» обновлены.",
        ]);
    }
}
