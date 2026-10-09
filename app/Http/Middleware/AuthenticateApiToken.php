<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Bearer-token auth for the mobile app: tokens live hashed on users.api_token. */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?: $request->query('api_token');

        $user = $token !== null && $token !== ''
            ? User::where('api_token', hash('sha256', $token))->where('is_active', true)->first()
            : null;

        abort_if($user === null, 401);

        Auth::setUser($user);

        return $next($request);
    }
}
