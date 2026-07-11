<?php

namespace App\Support;

use Illuminate\Http\Request;

final class PlatformHost
{
    public static function domain(): string
    {
        return strtolower(trim((string) config('app.platform_domain', '')));
    }

    public static function matchesRequest(Request $request): bool
    {
        $domain = self::domain();

        if ($domain === '') {
            return false;
        }

        $host = strtolower($request->getHost());
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return strcasecmp($host, $domain) === 0;
    }

    public static function url(string $path = '/'): string
    {
        $domain = self::domain();

        if ($domain === '') {
            return $path;
        }

        $path = '/'.ltrim($path, '/');
        $scheme = parse_url((string) config('app.url', 'http://localhost'), PHP_URL_SCHEME) ?: 'http';

        return "{$scheme}://{$domain}{$path}";
    }
}
