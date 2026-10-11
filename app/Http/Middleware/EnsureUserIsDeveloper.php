<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Developer-only screens (branding, deployment settings) are hidden from every regular role, including Admin. */
class EnsureUserIsDeveloper
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isDeveloper() ?? false, 403);

        return $next($request);
    }
}
