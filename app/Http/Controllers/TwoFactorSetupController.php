<?php

namespace App\Http\Controllers;

use App\Models\SecuritySetting;
use App\Services\StaffIntendedUrl;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TwoFactorSetupController extends Controller
{
    public function show(Request $request, TwoFactorAuthenticationService $twoFactor, StaffIntendedUrl $intendedUrl): View
    {
        abort_unless($request->user()->isStaff(), 403);

        $secret = null;
        $qrCode = null;
        if (! $request->user()->hasConfirmedTwoFactor()) {
            $secret = $request->session()->get('two_factor.setup_secret');
            if (! is_string($secret) || $secret === '') {
                $secret = $twoFactor->generateSecret();
                $request->session()->put('two_factor.setup_secret', $secret);
            }

            $qrCode = (string) QrCode::format('svg')
                ->size(220)
                ->margin(1)
                ->backgroundColor(255, 255, 255)
                ->color(0, 0, 0)
                ->generate($twoFactor->provisioningUri($request->user(), $secret));
        }

        $continueUrl = $request->user()->hasConfirmedTwoFactor()
            ? $intendedUrl->pull($request, $request->user())
            : null;

        return view('auth.two-factor-setup', [
            'secret' => $secret,
            'qrCode' => $qrCode,
            'recoveryCodes' => $request->session()->pull('two_factor.new_recovery_codes'),
            'remainingRecoveryCodes' => count($request->user()->two_factor_recovery_codes ?? []),
            'twoFactorRequired' => SecuritySetting::requiresStaffTwoFactor(),
            'continueUrl' => $continueUrl,
        ]);
    }

    public function confirm(Request $request, TwoFactorAuthenticationService $twoFactor)
    {
        abort_unless($request->user()->isStaff(), 403);
        abort_unless(SecuritySetting::requiresStaffTwoFactor(), 409, '2FA sedang tidak diwajibkan oleh administrator.');
        abort_if($request->user()->hasConfirmedTwoFactor(), 409, '2FA sudah aktif.');

        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ], ['code.regex' => 'Kode autentikator harus terdiri dari 6 angka.']);

        $secret = $request->session()->get('two_factor.setup_secret');
        $verifiedStep = is_string($secret) ? $twoFactor->verifyTimestamp($secret, $data['code']) : false;
        if ($verifiedStep === false) {
            throw ValidationException::withMessages(['code' => 'Kode autentikator tidak valid atau sudah kedaluwarsa.']);
        }

        $codes = $twoFactor->generateRecoveryCodes();
        $request->user()->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($codes),
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used_step' => $verifiedStep,
        ])->save();

        $request->session()->forget('two_factor.setup_secret');
        $request->session()->put('two_factor.verified_at', now()->timestamp);
        $request->session()->flash('two_factor.new_recovery_codes', $codes);

        return redirect()->route('two-factor.setup.show')
            ->with('success', 'Autentikasi dua langkah berhasil diaktifkan. Simpan recovery code di tempat yang aman.');
    }

    public function regenerate(Request $request, TwoFactorAuthenticationService $twoFactor)
    {
        abort_unless($request->user()->isStaff() && $request->user()->hasConfirmedTwoFactor(), 403);

        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'Kata sandi saat ini tidak sesuai.']);
        }

        if (! $twoFactor->verifyForUser($request->user(), $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Kode autentikator tidak valid atau sudah kedaluwarsa.']);
        }

        $codes = $twoFactor->generateRecoveryCodes();
        $request->user()->forceFill([
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($codes),
        ])->save();
        $request->session()->flash('two_factor.new_recovery_codes', $codes);

        return redirect()->route('two-factor.setup.show')
            ->with('success', 'Recovery code lama dinonaktifkan dan telah diganti.');
    }
}
