<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After authentication: tenant from user.tenant_id is authoritative.
 * Rejects slug/header context that does not match the authenticated user.
 */
class SetTenantFromAuthenticatedUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $user->loadMissing('tenant');

        if ($user->tenant === null) {
            abort(403, 'User is not assigned to a tenant.');
        }

        $contextTenant = TenantContext::get();

        if ($contextTenant !== null && $contextTenant->id !== $user->tenant_id) {
            abort(403, 'Tenant context does not match authenticated user.');
        }

        TenantContext::set($user->tenant);

        return $next($request);
    }
}
