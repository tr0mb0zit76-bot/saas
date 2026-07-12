<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\PlatformHost;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PlatformHost::matchesRequest($request)) {
            TenantContext::bypass(true);

            return $next($request);
        }

        $slug = $request->header('X-Tenant-Slug')
            ?? $this->resolveSubdomain($request->getHost())
            ?? config('saas.default_tenant_slug');

        $tenant = Tenant::query()
            ->where('slug', $slug)
            ->whereIn('status', ['active', 'trial', 'suspended'])
            ->first();

        if ($tenant === null) {
            abort(404, 'Tenant not found or inactive.');
        }

        TenantContext::set($tenant);

        return $next($request);
    }

    private function resolveSubdomain(string $host): ?string
    {
        $host = strtolower($host);
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        if (in_array($host, ['localhost', '127.0.0.1', 'saas.local'], true)) {
            return null;
        }

        $parts = explode('.', $host);
        if (count($parts) < 3) {
            return null;
        }

        return $parts[0] !== 'www' ? $parts[0] : null;
    }
}
