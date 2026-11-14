<?php

namespace App\Http\Middleware;

use App\Support\PlatformAdmin;
use App\Support\PlatformHost;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PlatformAdmin::isPlatformAdmin($request->user())) {
            return $next($request);
        }

        // Shared SESSION_DOMAIN (e.g. .traklo.pro) often leaves a tenant CRM
        // session on the platform host — do not trap that user behind a 403 wall.
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson()) {
            abort(403, 'Platform administrator access required.');
        }

        return redirect()
            ->guest(PlatformHost::url('/login'))
            ->withErrors([
                'email' => 'Этот портал только для операторов платформы. Войдите под разрешённой учётной записью.',
            ]);
    }
}
