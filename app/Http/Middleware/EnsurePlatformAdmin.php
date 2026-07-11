<?php

namespace App\Http\Middleware;

use App\Support\PlatformAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PlatformAdmin::isPlatformAdmin($request->user())) {
            abort(403, 'Platform administrator access required.');
        }

        return $next($request);
    }
}
