<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::lower($credentials['username']).'|api|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['username' => __('TooManyAttempts')]);
        }

        if (! Auth::validate([...$credentials, 'is_active' => true])) {
            RateLimiter::hit($key, 300);
            ActivityLog::record('login_failed', null, $credentials['username']);
            throw ValidationException::withMessages(['username' => __('InvalidLogin')]);
        }

        RateLimiter::clear($key);
        $user = User::where('username', $credentials['username'])->firstOrFail();

        $token = Str::random(60);
        $user->forceFill(['api_token' => hash('sha256', $token)])->save();

        return response()->json([
            'id' => $user->id,
            'name' => $user->full_name ?: $user->username,
            'username' => $user->username,
            'role' => $user->role->value,
            'token' => $token,
        ]);
    }
}
