<?php

namespace App\Http\Middleware;

use App\Support\PlatformHost;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform portal lives on platform.{crm_domain}; Ziggy/URL must use request host, not APP_URL.
 */
class ForcePlatformRootUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PlatformHost::matchesRequest($request)) {
            URL::forceRootUrl($request->getScheme().'://'.$request->getHost());
        }

        return $next($request);
    }
}
