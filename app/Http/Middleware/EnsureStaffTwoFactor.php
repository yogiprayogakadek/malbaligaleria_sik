<?php

namespace App\Http\Middleware;

use App\Models\SecuritySetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isStaff() || ! SecuritySetting::requiresStaffTwoFactor() || $request->routeIs('logout', 'password.required.*', 'two-factor.setup.*')) {
            return $next($request);
        }

        if (! $user->hasConfirmedTwoFactor()) {
            return redirect()->route('two-factor.setup.show');
        }

        if ($request->session()->has('two_factor.verified_at')) {
            return $next($request);
        }

        $request->session()->put([
            'two_factor.user_id' => $user->id,
            'two_factor.remember' => false,
        ]);
        Auth::logout();

        return redirect()->route('two-factor.challenge.show');
    }
}
