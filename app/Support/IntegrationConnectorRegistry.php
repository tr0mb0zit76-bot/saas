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
        $adapters = config('saas-connectors.adapters', []);
        if (! is_array($adapters)) {
            return [];
        }

        $options = [];
        foreach ($adapters as $key => $meta) {
            if (! is_string($key) || $key === '' || ! is_array($meta)) {
                continue;
            }
            $options[] = [
                'key' => $key,
                'label' => (string) ($meta['label'] ?? $key),
                'description' => (string) ($meta['description'] ?? ''),
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
