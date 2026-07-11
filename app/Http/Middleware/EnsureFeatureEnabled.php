<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = TenantContext::get();

        if ($tenant === null || ! $tenant->featureEnabled($feature)) {
            abort(403, 'This feature is not available on your plan.');
        }

        return $next($request);
    }
}
