<?php

namespace App\Http\Middleware;

use App\Models\SecuritySetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class RequireSensitiveActionConfirmation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! SecuritySetting::current()->sensitive_actions_require_2fa) {
            return $next($request);
        }

        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);
        if (! $user->hasConfirmedTwoFactor()) {
            return redirect()->route('two-factor.setup.show')->with('info', 'Aktifkan 2FA sebelum mengubah konfigurasi sensitif.');
        }

        $confirmedAt = (int) $request->session()->get('sensitive_action.confirmed_at', 0);
        if ($confirmedAt >= now()->subMinutes(10)->timestamp) {
            return $next($request);
        }

        if (! $request->isMethod('GET')) {
            throw ValidationException::withMessages([
                'sensitive_action' => 'Konfirmasi 2FA untuk perubahan sensitif telah kedaluwarsa. Buka kembali halaman pengaturan lalu verifikasi ulang.',
            ]);
        }

        $request->session()->put('sensitive_action.intended', $request->fullUrl());

        return redirect()->route('admin.sensitive-confirm.show');
    }
}
