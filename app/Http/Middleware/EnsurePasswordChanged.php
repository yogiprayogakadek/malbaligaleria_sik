<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['login' => 'Akun Anda sudah dinonaktifkan. Hubungi administrator.']);
        }

        if (! $user->must_change_password || $request->routeIs('password.required.*', 'logout')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Kata sandi awal harus diganti sebelum akun dapat digunakan.',
                'redirect' => route('password.required.edit'),
            ], 409);
        }

        return redirect()->route('password.required.edit');
    }
}
