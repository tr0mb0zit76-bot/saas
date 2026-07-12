<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StorePlatformTenantRequest;
use App\Http\Requests\Platform\UpdatePlatformTenantFeaturesRequest;
use App\Http\Requests\Platform\UpdatePlatformTenantRequest;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\Saas\TenantAuditLogger;
use App\Services\Saas\TenantBillingService;
use App\Services\Saas\TenantOnboardingService;
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
        private readonly TenantOnboardingService $onboarding,
        private readonly TenantAuditLogger $auditLogger,
    ) {}

    public function index(): Response
    {
        TenantContext::bypass(true);

        $tenants = Tenant::query()
            ->with(['subscription', 'invoices' => fn ($query) => $query->latest('id')->limit(1)])
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
                'latest_invoice' => $tenant->invoices->first() ? [
                    'id' => $tenant->invoices->first()->id,
                    'invoice_number' => $tenant->invoices->first()->invoice_number,
                    'status' => $tenant->invoices->first()->status,
                ] : null,
                'features' => $tenant->enabledFeatures(),
                'created_at' => $tenant->created_at?->toIso8601String(),
            ])
            ->values();

        TenantContext::bypass(false);

        return Inertia::render('Platform/Tenants/Index', [
            'tenants' => $tenants,
            'planOptions' => SubscriptionPlan::selectOptions(),
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

        $onboarded = $this->onboarding->createAdminUser(
            $tenant,
            $request->string('admin_name')->toString(),
            $request->string('admin_email')->toString(),
        );

        if ($request->boolean('send_invite', true)) {
            $this->onboarding->sendWelcomeInvite($tenant, $onboarded['user'], $onboarded['password']);
        }

        $this->auditLogger->log(
            $tenant->id,
            $request->user()?->id,
            'tenant.created',
            'tenant',
            $tenant->id,
            null,
            [
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'status' => $tenant->status,
                'plan' => $tenant->planKey(),
                'admin_email' => $onboarded['user']->email,
            ],
        );

        TenantContext::bypass(false);

        $inviteNote = $request->boolean('send_invite', true)
            ? ' Приглашение отправлено на '.$onboarded['user']->email.'.'
            : ' Временный пароль создан (письмо не отправлялось).';

        return to_route('platform.tenants.index')->with('flash', [
            'type' => 'success',
            'message' => "Арендатор «{$tenant->name}» создан. Администратор: {$onboarded['user']->email}.{$inviteNote}",
        ]);
    }

    public function update(UpdatePlatformTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        TenantContext::bypass(true);

        $oldValues = [
            'name' => $tenant->name,
            'status' => $tenant->status,
            'plan' => $tenant->planKey(),
            'trial_ends_at' => $tenant->trial_ends_at?->toDateString(),
        ];

        $tenant->update([
            'name' => $request->string('name')->toString(),
            'status' => $request->string('status')->toString(),
            'plan' => $request->string('plan')->toString(),
            'trial_ends_at' => $request->date('trial_ends_at'),
        ]);

        $tenant->refresh();

        $this->auditLogger->log(
            $tenant->id,
            $request->user()?->id,
            'tenant.updated',
            'tenant',
            $tenant->id,
            $oldValues,
            [
                'name' => $tenant->name,
                'status' => $tenant->status,
                'plan' => $tenant->planKey(),
                'trial_ends_at' => $tenant->trial_ends_at?->toDateString(),
            ],
        );

        $this->provisioner->syncSubscription($tenant);

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

        $tenant->refresh()->load('subscription');

        $this->auditLogger->log(
            $tenant->id,
            request()->user()?->id,
            'tenant.invoice_paid',
            'tenant',
            $tenant->id,
            null,
            [
                'plan' => $tenant->planKey(),
                'subscription_status' => $tenant->subscription?->status,
                'billing_period_end' => $tenant->subscription?->billing_period_end?->toDateString(),
            ],
        );

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
        $oldOverrides = data_get($settings, 'features');
        $oldOverrides = is_array($oldOverrides) && ! array_is_list($oldOverrides) ? $oldOverrides : [];

        if ($overrides === []) {
            unset($settings['features']);
        } else {
            $settings['features'] = $overrides;
        }

        $tenant->update(['settings' => $settings]);

        $this->auditLogger->log(
            $tenant->id,
            $request->user()?->id,
            'tenant.features_updated',
            'tenant',
            $tenant->id,
            ['features' => $oldOverrides],
            ['features' => $overrides],
        );

        TenantContext::bypass(false);

        return to_route('platform.tenants.features', $tenant)->with('flash', [
            'type' => 'success',
            'message' => "Модули арендатора «{$tenant->name}» обновлены.",
        ]);
    }
}
