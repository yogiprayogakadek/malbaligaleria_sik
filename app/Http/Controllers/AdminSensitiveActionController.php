<?php

namespace App\Http\Controllers;

use App\Models\SecuritySetting;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminSensitiveActionController extends Controller
{
    public function show(): View
    {
        abort_unless(SecuritySetting::current()->sensitive_actions_require_2fa, 404);

        return view('admin.sensitive-confirm');
    }

    public function verify(Request $request, TwoFactorAuthenticationService $twoFactor): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        if (! $twoFactor->verifyForUser($request->user(), $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Kode autentikator tidak valid atau sudah digunakan.']);
        }

        $request->session()->put('sensitive_action.confirmed_at', now()->timestamp);
        $intended = $request->session()->pull('sensitive_action.intended', route('admin.dashboard'));

        return redirect()->to($intended)->with('success', 'Konfirmasi keamanan berlaku selama 10 menit.');
    }
}
