<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTenantIntegrationConnectorRequest;
use App\Http\Requests\UpdateTenantIntegrationConnectorRequest;
use App\Services\Saas\TenantConnectorService;
use App\Support\IntegrationConnectorRegistry;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class SettingsIntegrationsController extends Controller
{
    public function __construct(
        private readonly TenantConnectorService $connectors,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        $tenant = TenantContext::get() ?? $request->user()?->tenant;
        abort_if($tenant === null, 404);

        $featureEnabled = $tenant->featureEnabled('integrations');
        $canManage = RoleAccess::userHasRoleName($request->user(), 'admin') && $featureEnabled;

        return Inertia::render('Settings/Integrations/Index', [
            'feature_enabled' => $featureEnabled,
            'can_manage' => $canManage,
            'catalog' => IntegrationConnectorRegistry::catalogForTenant(),
            'connectors' => $featureEnabled ? $this->connectors->listSafe($tenant) : [],
            'upsell' => [
                'title' => 'Интеграции доступны на Enterprise',
                'body' => 'Подключение 1С и банковских коннекторов входит в тариф Enterprise. Обратитесь в поддержку Traklo, чтобы расширить план.',
            ],
        ]);
    }

    public function store(StoreTenantIntegrationConnectorRequest $request): RedirectResponse
    {
        $this->assertCanManage($request);

        $tenant = TenantContext::get() ?? $request->user()?->tenant;
        abort_if($tenant === null, 404);

        $validated = $request->validated();

        try {
            $this->connectors->bind(
                tenant: $tenant,
                adapterKey: (string) $validated['adapter_key'],
                endpoints: is_array($validated['endpoints'] ?? null) ? $validated['endpoints'] : [],
                mapping: is_array($validated['mapping'] ?? null) ? $validated['mapping'] : [],
                secrets: is_array($validated['secrets'] ?? null) ? $validated['secrets'] : [],
                enabled: (bool) ($validated['enabled'] ?? true),
                tenantSelfServe: true,
                managedBy: 'tenant',
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return to_route('settings.integrations.index')->with('flash', [
            'type' => 'success',
            'message' => 'Коннектор добавлен.',
        ]);
    }

    public function update(UpdateTenantIntegrationConnectorRequest $request, string $connector): RedirectResponse
    {
        $this->assertCanManage($request);

        $tenant = TenantContext::get() ?? $request->user()?->tenant;
        abort_if($tenant === null, 404);

        $validated = $request->validated();
        $existing = collect($this->connectors->listSafe($tenant))
            ->first(fn (array $row): bool => $row['id'] === $connector);
        abort_if($existing === null, 404);

        try {
            if (array_key_exists('enabled', $validated) && count($validated) === 1) {
                $this->connectors->setEnabled($tenant, $connector, (bool) $validated['enabled'], true);
            } else {
                $this->connectors->bind(
                    tenant: $tenant,
                    adapterKey: (string) $existing['adapter_key'],
                    endpoints: is_array($validated['endpoints'] ?? null) ? $validated['endpoints'] : $existing['endpoints'],
                    mapping: is_array($validated['mapping'] ?? null) ? $validated['mapping'] : $existing['mapping'],
                    secrets: is_array($validated['secrets'] ?? null) ? $validated['secrets'] : [],
                    enabled: (bool) ($validated['enabled'] ?? $existing['enabled']),
                    connectorId: $connector,
                    tenantSelfServe: true,
                    managedBy: 'tenant',
                );
            }
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return to_route('settings.integrations.index')->with('flash', [
            'type' => 'success',
            'message' => 'Коннектор обновлён.',
        ]);
    }

    public function destroy(Request $request, string $connector): RedirectResponse
    {
        $this->assertCanManage($request);

        $tenant = TenantContext::get() ?? $request->user()?->tenant;
        abort_if($tenant === null, 404);

        try {
            $this->connectors->unbind($tenant, $connector, true);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return to_route('settings.integrations.index')->with('flash', [
            'type' => 'success',
            'message' => 'Коннектор удалён.',
        ]);
    }

    public function test(Request $request, string $connector): JsonResponse|RedirectResponse
    {
        $this->assertCanManage($request);

        $tenant = TenantContext::get() ?? $request->user()?->tenant;
        abort_if($tenant === null, 404);

        try {
            $result = $this->connectors->configCheck($tenant, $connector);
        } catch (Throwable $e) {
            $result = ['status' => 'config_incomplete', 'message' => $e->getMessage()];
        }

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return back()->with('flash', [
            'type' => $result['status'] === 'config_incomplete' ? 'error' : 'success',
            'message' => $result['message'],
        ]);
    }

    private function assertCanManage(Request $request): void
    {
        abort_unless(RoleAccess::userHasRoleName($request->user(), 'admin'), 403);

        $tenant = TenantContext::get() ?? $request->user()?->tenant;
        abort_if($tenant === null, 404);
        abort_unless($tenant->featureEnabled('integrations'), 403);
    }
}
