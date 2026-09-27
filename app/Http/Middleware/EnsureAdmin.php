<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['login' => 'Akun Anda sudah dinonaktifkan. Hubungi administrator.']);
        }

        abort_unless(
            $request->user()?->isAdmin(),
            403,
            'Akses ditolak. Halaman ini khusus administrator aktif.',
        );

        return $next($request);
    }
}
