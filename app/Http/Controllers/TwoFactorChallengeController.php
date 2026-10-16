<?php

namespace App\Http\Controllers;

use App\Models\SecuritySetting;
use App\Models\User;
use App\Services\StaffLoginSecurityService;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TwoFactorChallengeController extends Controller
{
    public function show(Request $request)
    {
        if (! SecuritySetting::requiresStaffTwoFactor()) {
            $this->clearPendingChallenge($request);

            return redirect()->route('login')->with('info', 'Autentikasi dua langkah sedang tidak diwajibkan. Silakan masuk kembali.');
        }

        if (Auth::check()) {
            return redirect()->route(Auth::user()->dashboardRouteName());
        }

        $this->pendingUser($request);

        return view('auth.two-factor-challenge');
    }

    public function verify(
        Request $request,
        TwoFactorAuthenticationService $twoFactor,
        StaffLoginSecurityService $loginSecurity,
    ) {
        if (! SecuritySetting::requiresStaffTwoFactor()) {
            $this->clearPendingChallenge($request);

            return redirect()->route('login')->with('info', 'Autentikasi dua langkah sedang tidak diwajibkan. Silakan masuk kembali.');
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);
        $user = $this->pendingUser($request);
        $code = trim($data['code']);

        $valid = $twoFactor->verifyForUser($user, preg_replace('/\D/', '', $code));
        if (! $valid) {
            $valid = $twoFactor->consumeRecoveryCode($user, $code);
        }

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => 'Kode verifikasi tidak valid atau sudah digunakan.',
            ]);
        }

        $remember = (bool) $request->session()->pull('two_factor.remember', false);
        $request->session()->forget('two_factor.user_id');
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put('two_factor.verified_at', now()->timestamp);
        $loginSecurity->completeLogin($request, $user);

        return redirect()->route($user->dashboardRouteName())
            ->with('success', 'Verifikasi keamanan berhasil.');
    }

    private function pendingUser(Request $request): User
    {
        $userId = $request->session()->get('two_factor.user_id');
        $user = $userId ? User::query()->find($userId) : null;

        if (! $user || ! $user->is_active || ! $user->isStaff() || ! $user->hasConfirmedTwoFactor()) {
            $request->session()->forget(['two_factor.user_id', 'two_factor.remember']);
            abort(403, 'Sesi verifikasi tidak tersedia. Silakan masuk kembali.');
        }

        return $user;
    }

    private function clearPendingChallenge(Request $request): void
    {
        $request->session()->forget(['two_factor.user_id', 'two_factor.remember']);
    }
}
