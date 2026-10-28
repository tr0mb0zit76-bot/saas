<?php

namespace App\Http\Middleware;

use App\Support\PlatformHost;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform host serves platform routes only. CRM pages stay on the tenant host.
 */
class AbortCrmOnPlatformHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PlatformHost::matchesRequest($request)) {
            return $next($request);
        }

        $routeDomain = $request->route()?->getDomain();

        if (is_string($routeDomain) && strcasecmp($routeDomain, PlatformHost::domain()) === 0) {
            return $next($request);
        }

        abort(404);
    }
}
