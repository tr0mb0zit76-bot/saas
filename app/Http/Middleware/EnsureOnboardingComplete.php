<?php

namespace App\Http\Middleware;

use App\Support\PlatformHost;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    /** @var list<string> */
    private const SKIP_ROUTE_PREFIXES = [
        'onboarding.',
        'logout',
        'password.',
        'verification.',
        'profile.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (PlatformHost::matchesRequest($request) || ! $request->user()) {
            return $next($request);
        }

        $tenant = TenantContext::get();

        if ($tenant === null || $tenant->onboardingCompleted() || $tenant->isReadOnly()) {
            return $next($request);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');

        foreach (self::SKIP_ROUTE_PREFIXES as $prefix) {
            if ($routeName === $prefix || str_starts_with($routeName, $prefix)) {
                return $next($request);
            }
        }

        if ($routeName === 'onboarding.show' || $routeName === 'onboarding.store') {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Complete onboarding first.');
        }

        return redirect()->route('onboarding.show');
    }
}
