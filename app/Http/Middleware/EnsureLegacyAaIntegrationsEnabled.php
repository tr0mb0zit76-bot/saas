<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quarantine AA Astral/1C-Fresh product routes (ADR-015 / refinement backlog).
 */
class EnsureLegacyAaIntegrationsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! filter_var(config('saas.legacy_aa_integrations_enabled'), FILTER_VALIDATE_BOOL)) {
            abort(404);
        }

        return $next($request);
    }
}
