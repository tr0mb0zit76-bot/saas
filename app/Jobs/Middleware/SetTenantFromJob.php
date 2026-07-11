<?php

namespace App\Jobs\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;

class SetTenantFromJob
{
    /**
     * @param  array{tenantId?: int|null, tenant_id?: int|null}  $job
     */
    public function handle(object $job, Closure $next): mixed
    {
        $tenantId = $job->tenantId ?? $job->tenant_id ?? null;

        if ($tenantId === null) {
            return $next($job);
        }

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            return $next($job);
        }

        return TenantContext::runAs($tenant, fn () => $next($job));
    }
}
