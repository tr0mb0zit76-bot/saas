<?php

namespace App\Support;

use InvalidArgumentException;

final class IntegrationConnectorRegistry
{
    /**
     * @return list<string>
     */
    public static function adapterKeys(): array
    {
        $adapters = config('saas-connectors.adapters', []);

        if (! is_array($adapters)) {
            return [];
        }

        return array_values(array_filter(
            array_keys($adapters),
            static fn (mixed $key): bool => is_string($key) && $key !== '',
        ));
    }

    public static function assertAllowed(string $adapterKey): void
    {
        if (! in_array($adapterKey, self::adapterKeys(), true)) {
            throw new InvalidArgumentException("Adapter [{$adapterKey}] is not in the allowlist.");
        }
    }

    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function adapterOptions(): array
    {
        return array_map(
            static fn (array $row): array => [
                'key' => $row['key'],
                'label' => $row['label'],
                'description' => $row['description'],
            ],
            self::catalogEntries(),
        );
    }

    /**
     * Tenant Settings → Интеграция catalog (ADR-026). Ops may still bind any allowlisted key.
     *
     * @return list<array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     vendor: string,
     *     category: string,
     *     tenant_self_serve: bool,
     *     runtime_status: string,
     *     auth_model: string,
     *     docs_open: bool,
     *     docs_url: ?string,
     *     catalog_slug: string,
     *     onboarding_checklist: list<string>
     * }>
     */
    public static function catalogForTenant(): array
    {
        return array_values(array_filter(
            self::catalogEntries(),
            static fn (array $row): bool => $row['tenant_self_serve'] === true,
        ));
    }

    public static function assertTenantBindable(string $adapterKey): void
    {
        self::assertAllowed($adapterKey);

        $meta = config("saas-connectors.adapters.{$adapterKey}");
        if (! is_array($meta) || ! ($meta['tenant_self_serve'] ?? false)) {
            throw new InvalidArgumentException("Adapter [{$adapterKey}] is not available for tenant self-serve.");
        }
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     vendor: string,
     *     category: string,
     *     tenant_self_serve: bool,
     *     runtime_status: string,
     *     auth_model: string,
     *     docs_open: bool,
     *     docs_url: ?string,
     *     catalog_slug: string,
     *     onboarding_checklist: list<string>
     * }>
     */
    private static function catalogEntries(): array
    {
        $adapters = config('saas-connectors.adapters', []);
        if (! is_array($adapters)) {
            return [];
        }

        $options = [];
        foreach ($adapters as $key => $meta) {
            if (! is_string($key) || $key === '' || ! is_array($meta)) {
                continue;
            }

            $checklist = $meta['onboarding_checklist'] ?? [];
            $docsUrl = $meta['docs_url'] ?? null;

            $options[] = [
                'key' => $key,
                'label' => (string) ($meta['label'] ?? $key),
                'description' => (string) ($meta['description'] ?? ''),
                'vendor' => (string) ($meta['vendor'] ?? ''),
                'category' => (string) ($meta['category'] ?? 'other'),
                'tenant_self_serve' => (bool) ($meta['tenant_self_serve'] ?? false),
                'runtime_status' => (string) ($meta['runtime_status'] ?? 'stub'),
                'auth_model' => (string) ($meta['auth_model'] ?? 'unknown'),
                'docs_open' => (bool) ($meta['docs_open'] ?? false),
                'docs_url' => is_string($docsUrl) && $docsUrl !== '' ? $docsUrl : null,
                'catalog_slug' => (string) ($meta['catalog_slug'] ?? $key),
                'onboarding_checklist' => is_array($checklist)
                    ? array_values(array_filter($checklist, static fn (mixed $item): bool => is_string($item) && $item !== ''))
                    : [],
            ];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function template(string $templateKey): ?array
    {
        $template = config("saas-connectors.templates.{$templateKey}");

        return is_array($template) ? $template : null;
    }
}
