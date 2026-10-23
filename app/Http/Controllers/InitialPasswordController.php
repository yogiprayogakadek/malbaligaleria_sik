<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeInitialPasswordRequest;
use App\Models\SecuritySetting;
use App\Services\StaffIntendedUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InitialPasswordController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route($request->user()->dashboardRouteName());
        }

        return view('auth.change-initial-password');
    }

    public function update(ChangeInitialPasswordRequest $request, StaffIntendedUrl $intendedUrl): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => $request->validated('password'),
            'must_change_password' => false,
            'password_changed_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        $request->session()->regenerate();

        if (SecuritySetting::requiresStaffTwoFactor() && $user->isStaff() && ! $request->session()->has('two_factor.verified_at')) {
            return redirect()->route($user->hasConfirmedTwoFactor()
                ? $user->dashboardRouteName()
                : 'two-factor.setup.show')
                ->with('success', 'Kata sandi berhasil diperbarui. Lanjutkan verifikasi keamanan akun.');
        }

        return redirect()->to($intendedUrl->pull($request, $user) ?? route($user->dashboardRouteName()))
            ->with('success', 'Kata sandi berhasil diperbarui. Akun Anda sekarang dapat digunakan.');
    }
}
