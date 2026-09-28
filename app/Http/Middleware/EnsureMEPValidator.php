<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureMEPValidator
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['login' => 'Akun Anda sudah dinonaktifkan. Hubungi administrator.']);
        }

        abort_unless(
            $user->isValidator() && $user->division === 'MEP',
            403,
            'Akses ditolak. Halaman ini khusus validator divisi MEP.',
        );

        return $next($request);
    }
}
