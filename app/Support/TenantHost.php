<?php

namespace App\Support;

final class TenantHost
{
    public static function url(string $slug, string $path = '/'): string
    {
        $path = '/'.ltrim($path, '/');
        $scheme = parse_url((string) config('app.url', 'http://localhost'), PHP_URL_SCHEME) ?: 'http';
        $crmDomain = strtolower(trim((string) config('app.crm_domain', '')));

        // Lab same-host (saas.local / localhost): no tenant subdomain in the URL.
        if ($crmDomain === '' || in_array($crmDomain, ['localhost', '127.0.0.1', 'saas.local'], true)) {
            $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');

            return "{$baseUrl}{$path}";
        }

        // Production / multi-host: {slug}.{CRM_DOMAIN} — never strip labels from a
        // two-part apex (traklo.pro → .pro was generating dead invite links).
        return "{$scheme}://{$slug}.{$crmDomain}{$path}";
    }
}
