<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Абсолютный URL главной витрины.
 *
 * On tenant hosts ({slug}.{CRM_DOMAIN}) this must never be relative `/` —
 * that would open the tenant root instead of the marketing apex.
 */
final class ShowcaseUrl
{
    public static function home(?Request $request = null): string
    {
        $request ??= request();

        /** @var list<string> $hosts */
        $hosts = config('app.showcase_hosts', []);
        $hosts = array_values(array_filter(array_map(
            static fn (string $h): string => strtolower(trim($h)),
            is_array($hosts) ? $hosts : [],
        )));

        $primary = $hosts[0] ?? strtolower(trim((string) config('app.crm_domain', '')));

        // Bare local loopback without a configured host — relative is fine.
        if ($primary === '' || in_array($primary, ['localhost', '127.0.0.1'], true)) {
            return '/';
        }

        $secure = $request instanceof Request
            ? $request->isSecure()
            : str_starts_with(strtolower((string) config('app.url')), 'https://');
        $scheme = $secure ? 'https' : 'http';

        // Always absolute apex (incl. saas.local / traklo.pro) so tenant
        // subdomains never resolve the logo to their own `/`.
        return sprintf('%s://%s/', $scheme, $primary);
    }
}
