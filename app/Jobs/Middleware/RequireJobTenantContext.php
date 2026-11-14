<?php

namespace App\Jobs\Middleware;

use App\Support\TenantContext;
use Closure;
use RuntimeException;

/**
 * Fail-closed: connector/sync jobs must run inside TenantContext (ADR-010 / ADR-022).
 */
class RequireJobTenantContext
{
    public function handle(object $job, Closure $next): mixed
    {
        if (TenantContext::id() === null && ! TenantContext::isBypassed()) {
            throw new RuntimeException('TenantContext is required for this job.');
        }

        return $next($job);
    }
}
