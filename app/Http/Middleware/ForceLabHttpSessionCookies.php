<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lab HTTP (APP_URL=http://…): session + XSRF cookies must never be Secure.
 * OSPanel may send X-Forwarded-Proto: https while the browser uses plain http://
 * → Secure cookies not sent → intermittent 419 on POST (logout, profile, etc.).
 */
class ForceLabHttpSessionCookies
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOL)
            && str_starts_with(strtolower((string) config('app.url', '')), 'http://')) {
            config(['session.secure' => false]);
        }

        return $next($request);
    }
}
