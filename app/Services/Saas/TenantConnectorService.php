<?php

namespace App\Services\Saas;

use App\Models\Tenant;
use App\Support\IntegrationConnectorRegistry;
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
        return array_map(function (array $row): array {
            $secrets = is_array($row['secrets'] ?? null) ? $row['secrets'] : [];

            return [
                'id' => (string) ($row['id'] ?? ''),
                'adapter_key' => (string) ($row['adapter_key'] ?? ''),
                'enabled' => (bool) ($row['enabled'] ?? false),
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
    ): array {
        IntegrationConnectorRegistry::assertAllowed($adapterKey);

        if (! $tenant->featureEnabled('integrations')) {
            throw new RuntimeException('feature:integrations is off for this tenant; settings cannot elevate.');
        }

        $list = $this->rawList($tenant);
        $id = $connectorId ?: (string) Str::uuid();
        $index = $this->indexOf($list, $id);

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

        $row = [
            'id' => $id,
            'adapter_key' => $adapterKey,
            'enabled' => $enabled,
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

        $safe = collect($this->listForPlatform($tenant->fresh()))
            ->first(fn (array $item): bool => $item['id'] === $id);

        return is_array($safe) ? $safe : [
            'id' => $id,
            'adapter_key' => $adapterKey,
            'enabled' => $enabled,
            'endpoints' => $endpoints,
            'mapping' => $mapping,
            'has_secrets' => $mergedSecrets !== [],
            'secret_keys' => array_values(array_filter(array_keys($mergedSecrets), 'is_string')),
        ];
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
