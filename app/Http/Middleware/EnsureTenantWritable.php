<?php

namespace App\Http\Middleware;

use App\Support\PlatformHost;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantWritable
{
    /** @var list<string> */
    private const ALLOWED_ROUTE_NAMES = [
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (PlatformHost::matchesRequest($request) || ! $request->user()) {
            return $next($request);
        }

        $tenant = TenantContext::get();

        if ($tenant === null || ! $tenant->isReadOnly()) {
            return $next($request);
        }

        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (is_string($routeName) && in_array($routeName, self::ALLOWED_ROUTE_NAMES, true)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->header('X-Inertia')) {
            abort(403, 'Арендатор приостановлен. Доступен только просмотр данных. Свяжитесь с поддержкой для возобновления.');
        }

        abort(403, 'Арендатор приостановлен. Доступен только просмотр.');
    }
}
