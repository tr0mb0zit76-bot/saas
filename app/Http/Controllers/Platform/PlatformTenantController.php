<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StorePlatformTenantRequest;
use App\Http\Requests\Platform\UpdatePlatformTenantFeaturesRequest;
use App\Http\Requests\Platform\UpdatePlatformTenantOpsRequest;
use App\Http\Requests\Platform\UpdatePlatformTenantRequest;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\ContentPacks\SalesBookContentPackInstaller;
use App\Services\Saas\TenantAuditLogger;
use App\Services\Saas\TenantBillingService;
use App\Services\Saas\TenantConnectorService;
use App\Services\Saas\TenantOnboardingService;
use App\Services\Saas\TenantProvisioner;
use App\Support\IntegrationConnectorRegistry;
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
        private readonly SalesBookContentPackInstaller $salesBookPacks,
        private readonly TenantConnectorService $connectors,
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

    public function ops(Tenant $tenant): Response
    {
        TenantContext::bypass(true);

        $installed = data_get($tenant->settings, 'content_packs.sales_book', []);
        $installed = is_array($installed) ? array_values(array_filter($installed, 'is_string')) : [];

        $payload = [
            'tenant' => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'plan' => $tenant->planKey(),
            ],
            'document_verification' => $tenant->settingBool('print.document_verification', false),
            'installed_packs' => $installed,
            'available_packs' => array_map(
                static fn (string $slug): array => ['slug' => $slug, 'installed' => in_array($slug, $installed, true)],
                SalesBookContentPackInstaller::knownPacks(),
            ),
            'integrations_enabled' => $tenant->featureEnabled('integrations'),
            'adapters' => IntegrationConnectorRegistry::adapterOptions(),
            'connectors' => $this->connectors->listForPlatform($tenant),
        ];

        TenantContext::bypass(false);

        return Inertia::render('Platform/Tenants/Ops', $payload);
    }

    public function updateOps(UpdatePlatformTenantOpsRequest $request, Tenant $tenant): RedirectResponse
    {
        TenantContext::bypass(true);

        $validated = $request->validated();
        $messages = [];

        if (array_key_exists('document_verification', $validated)) {
            $settings = is_array($tenant->settings) ? $tenant->settings : [];
            $old = (bool) data_get($settings, 'print.document_verification', false);
            $new = (bool) $validated['document_verification'];
            data_set($settings, 'print.document_verification', $new);
            $tenant->update(['settings' => $settings]);
            $this->auditLogger->log(
                $tenant->id,
                $request->user()?->id,
                'tenant.print_verification_updated',
                'tenant',
                $tenant->id,
                ['document_verification' => $old],
                ['document_verification' => $new],
            );
            $messages[] = $new ? 'Проверка документов включена.' : 'Проверка документов выключена.';
        }

        if (! empty($validated['install_pack'])) {
            $pack = (string) $validated['install_pack'];
            $reset = (bool) ($validated['reset_pack'] ?? false);
            $this->salesBookPacks->install($tenant->fresh(), $pack, $reset);
            $this->auditLogger->log(
                $tenant->id,
                $request->user()?->id,
                'tenant.content_pack_installed',
                'tenant',
                $tenant->id,
                null,
                ['pack' => $pack, 'reset' => $reset],
            );
            $messages[] = "Пакет «{$pack}» установлен.";
        }

        if (isset($validated['connector']) && is_array($validated['connector'])) {
            $connector = $validated['connector'];
            $tenant = $tenant->fresh();

            try {
                if (! empty($connector['clone_aa_template'])) {
                    $this->connectors->cloneFromTemplate($tenant, 'aa_one_c_bp');
                    $messages[] = 'Коннектор 1С клонирован из шаблона AA.';
                } else {
                    $endpoints = [];
                    $baseUrl = trim((string) ($connector['base_url'] ?? ''));
                    if ($baseUrl !== '') {
                        $endpoints['base_url'] = rtrim($baseUrl, '/');
                    }

                    $secrets = array_filter([
                        'username' => (string) ($connector['username'] ?? ''),
                        'password' => (string) ($connector['password'] ?? ''),
                    ], static fn (string $value): bool => $value !== '');

                    $this->connectors->bind(
                        $tenant,
                        (string) $connector['adapter_key'],
                        $endpoints,
                        [],
                        $secrets,
                        (bool) ($connector['enabled'] ?? true),
                        isset($connector['id']) && is_string($connector['id']) && $connector['id'] !== ''
                            ? $connector['id']
                            : null,
                    );
                    $messages[] = 'Коннектор сохранён.';
                }

                $this->auditLogger->log(
                    $tenant->id,
                    $request->user()?->id,
                    'tenant.connector_updated',
                    'tenant',
                    $tenant->id,
                    null,
                    [
                        'adapter_key' => $connector['adapter_key'] ?? 'one_c_bp',
                        'clone' => (bool) ($connector['clone_aa_template'] ?? false),
                    ],
                );
            } catch (\Throwable $e) {
                TenantContext::bypass(false);

                return to_route('platform.tenants.ops', $tenant)->with('flash', [
                    'type' => 'error',
                    'message' => $e->getMessage(),
                ]);
            }
        }

        TenantContext::bypass(false);

        return to_route('platform.tenants.ops', $tenant)->with('flash', [
            'type' => 'success',
            'message' => $messages !== [] ? implode(' ', $messages) : 'Без изменений.',
        ]);
    }
}
