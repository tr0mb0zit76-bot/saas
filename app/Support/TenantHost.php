<?php

namespace App\Support;

final class TenantHost
{
    public static function url(string $slug, string $path = '/'): string
    {
        $path = '/'.ltrim($path, '/');
        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'http';
        $host = parse_url($baseUrl, PHP_URL_HOST) ?: 'localhost';

        if (in_array($host, ['localhost', '127.0.0.1', 'saas.local'], true)) {
            return "{$baseUrl}{$path}";
        }

        $parts = explode('.', $host, 2);

        if (count($parts) === 2) {
            return "{$scheme}://{$slug}.{$parts[1]}{$path}";
        }

        return "{$scheme}://{$slug}.{$host}{$path}";
    }
}
