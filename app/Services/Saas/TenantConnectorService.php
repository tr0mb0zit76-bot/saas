<?php

namespace App\Services\Saas;

use App\Models\Tenant;
use App\Support\IntegrationConnectorRegistry;
use App\Support\IntegrationEndpointUrl;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class TenantConnectorService
{
    private const ENC_PREFIX = 'enc:';

    /**
     * @return list<array{
     *     id: string,
     *     adapter_key: string,
     *     enabled: bool,
     *     endpoints: array<string, mixed>,
     *     mapping: array<string, mixed>,
     *     has_secrets: bool,
     *     secret_keys: list<string>
     * }>
     */
    public function listForPlatform(Tenant $tenant): array
    {
        return $this->listSafe($tenant);
    }

    /**
     * Safe DTO for Platform Ops and tenant Settings (no secret values).
     *
     * @return list<array{
     *     id: string,
     *     adapter_key: string,
     *     label: string,
     *     enabled: bool,
     *     managed_by: string,
     *     runtime_status: string,
     *     endpoints: array<string, mixed>,
     *     mapping: array<string, mixed>,
     *     has_secrets: bool,
     *     secret_keys: list<string>
     * }>
     */
    public function listSafe(Tenant $tenant): array
    {
        $catalog = [];
        foreach (IntegrationConnectorRegistry::adapterOptions() as $option) {
            $catalog[$option['key']] = $option;
        }

        $metaByKey = [];
        foreach (config('saas-connectors.adapters', []) as $key => $meta) {
            if (is_string($key) && is_array($meta)) {
                $metaByKey[$key] = $meta;
            }
        }

        return array_map(function (array $row) use ($catalog, $metaByKey): array {
            $secrets = is_array($row['secrets'] ?? null) ? $row['secrets'] : [];
            $adapterKey = (string) ($row['adapter_key'] ?? '');
            $meta = $metaByKey[$adapterKey] ?? [];

            return [
                'id' => (string) ($row['id'] ?? ''),
                'adapter_key' => $adapterKey,
                'label' => (string) ($catalog[$adapterKey]['label'] ?? $adapterKey),
                'enabled' => (bool) ($row['enabled'] ?? false),
                'managed_by' => (string) ($row['managed_by'] ?? 'tenant'),
                'runtime_status' => (string) ($meta['runtime_status'] ?? 'stub'),
                'endpoints' => is_array($row['endpoints'] ?? null) ? $row['endpoints'] : [],
                'mapping' => is_array($row['mapping'] ?? null) ? $row['mapping'] : [],
                'has_secrets' => $secrets !== [],
                'secret_keys' => array_values(array_filter(array_keys($secrets), 'is_string')),
            ];
        }, $this->rawList($tenant));
    }

    /**
     * @param  array<string, mixed>  $endpoints
     * @param  array<string, mixed>  $mapping
     * @param  array<string, string>  $secrets  plaintext; empty values skipped / leave existing
     */
    public function bind(
        Tenant $tenant,
        string $adapterKey,
        array $endpoints = [],
        array $mapping = [],
        array $secrets = [],
        bool $enabled = true,
        ?string $connectorId = null,
        bool $tenantSelfServe = false,
        string $managedBy = 'platform',
    ): array {
        IntegrationConnectorRegistry::assertAllowed($adapterKey);

        if ($tenantSelfServe) {
            IntegrationConnectorRegistry::assertTenantBindable($adapterKey);
        }

        if (! $tenant->featureEnabled('integrations')) {
            throw new RuntimeException('feature:integrations is off for this tenant; settings cannot elevate.');
        }

        IntegrationEndpointUrl::assertEndpointsSafe($endpoints);

        $list = $this->rawList($tenant);
        $id = $connectorId ?: (string) Str::uuid();
        $index = $this->indexOf($list, $id);

        if ($index !== null && ($list[$index]['managed_by'] ?? 'tenant') === 'platform' && $tenantSelfServe) {
            throw new RuntimeException('Connector is managed by platform and cannot be changed by the tenant.');
        }

        $existingSecrets = $index !== null && is_array($list[$index]['secrets'] ?? null)
            ? $list[$index]['secrets']
            : [];

        $mergedSecrets = $existingSecrets;
        foreach ($secrets as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            $plain = trim((string) $value);
            if ($plain === '') {
                continue;
            }
            $mergedSecrets[$key] = $this->encrypt($plain);
        }

        $existingManagedBy = $index !== null
            ? (string) ($list[$index]['managed_by'] ?? $managedBy)
            : $managedBy;

        $row = [
            'id' => $id,
            'adapter_key' => $adapterKey,
            'enabled' => $enabled,
            'managed_by' => $tenantSelfServe ? 'tenant' : $existingManagedBy,
            'endpoints' => $endpoints,
            'mapping' => $mapping,
            'secrets' => $mergedSecrets,
        ];

        if ($index === null) {
            $list[] = $row;
        } else {
            $list[$index] = $row;
        }

        $this->persist($tenant, $list);

        $safe = collect($this->listSafe($tenant->fresh()))
            ->first(fn (array $item): bool => $item['id'] === $id);

        return is_array($safe) ? $safe : [
            'id' => $id,
            'adapter_key' => $adapterKey,
            'label' => $adapterKey,
            'enabled' => $enabled,
            'managed_by' => $row['managed_by'],
            'runtime_status' => 'stub',
            'endpoints' => $endpoints,
            'mapping' => $mapping,
            'has_secrets' => $mergedSecrets !== [],
            'secret_keys' => array_values(array_filter(array_keys($mergedSecrets), 'is_string')),
        ];
    }

    public function setEnabled(Tenant $tenant, string $connectorId, bool $enabled, bool $tenantSelfServe = false): array
    {
        if (! $tenant->featureEnabled('integrations')) {
            throw new RuntimeException('feature:integrations is off for this tenant; settings cannot elevate.');
        }

        $list = $this->rawList($tenant);
        $index = $this->indexOf($list, $connectorId);
        if ($index === null) {
            throw new InvalidArgumentException('Connector not found.');
        }

        if ($tenantSelfServe && ($list[$index]['managed_by'] ?? 'tenant') === 'platform') {
            throw new RuntimeException('Connector is managed by platform and cannot be changed by the tenant.');
        }

        $list[$index]['enabled'] = $enabled;
        $this->persist($tenant, $list);

        $safe = collect($this->listSafe($tenant->fresh()))
            ->first(fn (array $item): bool => $item['id'] === $connectorId);

        if (! is_array($safe)) {
            throw new InvalidArgumentException('Connector not found after update.');
        }

        return $safe;
    }

    public function unbind(Tenant $tenant, string $connectorId, bool $tenantSelfServe = false): void
    {
        if (! $tenant->featureEnabled('integrations')) {
            throw new RuntimeException('feature:integrations is off for this tenant; settings cannot elevate.');
        }

        $list = $this->rawList($tenant);
        $index = $this->indexOf($list, $connectorId);
        if ($index === null) {
            throw new InvalidArgumentException('Connector not found.');
        }

        if ($tenantSelfServe && ($list[$index]['managed_by'] ?? 'tenant') === 'platform') {
            throw new RuntimeException('Connector is managed by platform and cannot be changed by the tenant.');
        }

        array_splice($list, $index, 1);
        $this->persist($tenant, $list);
    }

    /**
     * P0: validate required config without live network call.
     *
     * @return array{status: string, message: string}
     */
    public function configCheck(Tenant $tenant, string $connectorId): array
    {
        $safe = collect($this->listSafe($tenant))
            ->first(fn (array $item): bool => $item['id'] === $connectorId);

        if (! is_array($safe)) {
            return ['status' => 'config_incomplete', 'message' => 'Коннектор не найден.'];
        }

        $adapterKey = $safe['adapter_key'];
        $endpoints = $safe['endpoints'];
        $hasSecrets = $safe['has_secrets'];
        $runtime = $safe['runtime_status'];

        if (in_array($adapterKey, ['t_bank', 'tochka', 'sber', 'alfa'], true) && ! $hasSecrets) {
            return ['status' => 'config_incomplete', 'message' => 'Нужен токен / OAuth-секрет.'];
        }

        if ($adapterKey === 'one_c_bp') {
            $base = (string) ($endpoints['base_url'] ?? data_get($endpoints, 'publications.*.base_url.0') ?? '');
            if ($base === '' && empty($endpoints['publications'])) {
                return ['status' => 'config_incomplete', 'message' => 'Укажите URL публикации OData.'];
            }
            if (! $hasSecrets) {
                return ['status' => 'config_incomplete', 'message' => 'Укажите логин и пароль OData.'];
            }
        }

        if ($adapterKey === 'vtb_bank') {
            $checklist = data_get($endpoints, 'onboarding', []);
            if (! is_array($checklist) || $checklist === []) {
                return ['status' => 'config_incomplete', 'message' => 'Заполните чеклист онбординга ИБК.'];
            }
        }

        if ($runtime === 'stub') {
            return [
                'status' => 'stub_not_live',
                'message' => 'Конфигурация сохранена. Живой ping пока недоступен (stub).',
            ];
        }

        return ['status' => 'config_ok', 'message' => 'Конфигурация выглядит полной.'];
    }

    /**
     * Clone AA template into Orientum (or any tenant) — encrypted secrets from env, not shared runtime .env.
     */
    public function cloneFromTemplate(Tenant $tenant, string $templateKey = 'aa_one_c_bp', bool $enabled = true): array
    {
        $template = IntegrationConnectorRegistry::template($templateKey);
        if ($template === null) {
            throw new InvalidArgumentException("Unknown connector template [{$templateKey}].");
        }

        $adapterKey = (string) ($template['adapter_key'] ?? '');
        IntegrationConnectorRegistry::assertAllowed($adapterKey);

        $endpoints = is_array($template['endpoints'] ?? null) ? $template['endpoints'] : [];
        foreach ($template['endpoint_env_overrides'] ?? [] as $dot => $envKey) {
            $fromEnv = (string) env((string) $envKey, '');
            if ($fromEnv !== '') {
                data_set($endpoints, (string) $dot, rtrim($fromEnv, '/'));
            }
        }

        $secrets = [];
        foreach ($template['secret_env_keys'] ?? [] as $secretKey => $envKey) {
            $fromEnv = (string) env((string) $envKey, '');
            if ($fromEnv !== '') {
                $secrets[(string) $secretKey] = $fromEnv;
            }
        }

        // Idempotent: reuse existing connector with same adapter_key if present.
        $existing = collect($this->rawList($tenant))
            ->first(fn (array $row): bool => ($row['adapter_key'] ?? '') === $adapterKey);

        $existingId = is_array($existing) && filled($existing['id'] ?? null)
            ? (string) $existing['id']
            : null;

        return $this->bind(
            $tenant,
            $adapterKey,
            $endpoints,
            is_array($template['mapping'] ?? null) ? $template['mapping'] : [],
            $secrets,
            $enabled,
            $existingId,
        );
    }

    /**
     * Decrypt secrets for job/runtime use only — never for Inertia.
     *
     * @return array<string, string>
     */
    public function decryptedSecrets(Tenant $tenant, string $connectorId): array
    {
        $row = collect($this->rawList($tenant))->first(
            fn (array $item): bool => (string) ($item['id'] ?? '') === $connectorId,
        );

        if (! is_array($row)) {
            throw new InvalidArgumentException('Connector not found.');
        }

        $out = [];
        foreach (is_array($row['secrets'] ?? null) ? $row['secrets'] : [] as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                continue;
            }
            $out[$key] = $this->decrypt($value);
        }

        return $out;
    }

    public function enabledConnector(Tenant $tenant, string $adapterKey): ?array
    {
        if (! $tenant->featureEnabled('integrations')) {
            return null;
        }

        foreach ($this->rawList($tenant) as $row) {
            if (($row['adapter_key'] ?? '') === $adapterKey && ($row['enabled'] ?? false)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rawList(Tenant $tenant): array
    {
        $list = data_get($tenant->settings, 'integrations', []);
        if (! is_array($list)) {
            return [];
        }

        return array_values(array_filter($list, 'is_array'));
    }

    /**
     * @param  list<array<string, mixed>>  $list
     */
    private function persist(Tenant $tenant, array $list): void
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $settings['integrations'] = array_values($list);
        $tenant->forceFill(['settings' => $settings])->save();
    }

    /**
     * @param  list<array<string, mixed>>  $list
     */
    private function indexOf(array $list, string $id): ?int
    {
        foreach ($list as $index => $row) {
            if ((string) ($row['id'] ?? '') === $id) {
                return $index;
            }
        }

        return null;
    }

    private function encrypt(string $plain): string
    {
        return self::ENC_PREFIX.Crypt::encryptString($plain);
    }

    private function decrypt(string $stored): string
    {
        if (! str_starts_with($stored, self::ENC_PREFIX)) {
            return $stored;
        }

        return Crypt::decryptString(substr($stored, strlen(self::ENC_PREFIX)));
    }
}
