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
            // Fail closed: business jobs must declare tenant context (ADR-010).
            // Opt out only via explicit public bool $allowWithoutTenant = true on the job.
            if (! (property_exists($job, 'allowWithoutTenant') && $job->allowWithoutTenant === true)) {
                throw new \RuntimeException('Job is missing tenantId; refusing to run without tenant context.');
            }

            return $next($job);
        }

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            // Fail closed for jobs that declared a tenant_id but it is missing.
            throw new \RuntimeException("Tenant [{$tenantId}] not found for job.");
        }

        return TenantContext::runAs($tenant, fn () => $next($job));
    }
}
