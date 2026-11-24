<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * SSRF guard for tenant-supplied connector endpoints (ADR-026).
 */
final class IntegrationEndpointUrl
{
    /**
     * @param  array<string, mixed>  $endpoints
     */
    public static function assertEndpointsSafe(array $endpoints): void
    {
        $urls = [];

        $base = $endpoints['base_url'] ?? null;
        if (is_string($base) && $base !== '') {
            $urls[] = $base;
        }

        $publications = $endpoints['publications'] ?? null;
        if (is_array($publications)) {
            foreach ($publications as $publication) {
                if (! is_array($publication)) {
                    continue;
                }
                $pubBase = $publication['base_url'] ?? null;
                if (is_string($pubBase) && $pubBase !== '') {
                    $urls[] = $pubBase;
                }
            }
        }

        foreach ($urls as $url) {
            self::assertPublicHttpUrl($url);
        }
    }

    public static function assertPublicHttpUrl(string $url): void
    {
        $url = trim($url);
        if ($url === '') {
            return;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('Endpoint URL must include scheme and host.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Endpoint URL scheme must be http or https.');
        }

        $host = strtolower((string) $parts['host']);
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new InvalidArgumentException('Endpoint URL host is not allowed.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('Endpoint URL must not target private or reserved IP ranges.');
            }

            return;
        }

        // Block obvious link-local / metadata hostnames.
        if (in_array($host, ['metadata.google.internal', 'metadata'], true)) {
            throw new InvalidArgumentException('Endpoint URL host is not allowed.');
        }
    }
}
