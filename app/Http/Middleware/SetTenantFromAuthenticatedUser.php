<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\InertiaAppSurface;
use App\Support\PlatformAdmin;
use App\Support\PlatformHost;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        if (PlatformHost::matchesRequest($request) && PlatformAdmin::isPlatformAdmin($user)) {
            TenantContext::bypass(true);

            return $next($request);
        }

        $user->loadMissing('tenant');

        if ($user->tenant === null) {
            // Shared SESSION_DOMAIN can leave a platform/tenantless session on the
            // marketing apex — show the landing, do not dump a raw 403.
            if (InertiaAppSurface::fromRequest($request) === InertiaAppSurface::Showcase) {
                return $next($request);
            }

            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                abort(403, 'User is not assigned to a tenant.');
            }

            $loginUrl = PlatformHost::domain() !== ''
                ? PlatformHost::url('/login')
                : url('/login');

            return redirect()
                ->guest($loginUrl)
                ->withErrors([
                    'email' => 'Учётная запись не привязана к компании. Войдите на поддомене вашей компании или через портал платформы.',
                ]);
        }

        $contextTenant = TenantContext::get();

        if ($contextTenant !== null && $contextTenant->id !== $user->tenant_id) {
            abort(403, 'Tenant context does not match authenticated user.');
        }

        TenantContext::bypass(false);
        TenantContext::set($user->tenant);

        return $next($request);
    }
}
