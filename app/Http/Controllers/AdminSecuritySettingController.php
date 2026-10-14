<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSecuritySettingRequest;
use App\Models\SecuritySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminSecuritySettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.security', [
            'setting' => SecuritySetting::current(),
        ]);
    }

    public function update(UpdateSecuritySettingRequest $request): RedirectResponse
    {
        SecuritySetting::query()->updateOrCreate(
            ['id' => 1],
            ['staff_two_factor_required' => $request->boolean('staff_two_factor_required')],
        );

        $request->session()->forget(['two_factor.user_id', 'two_factor.remember']);

        return redirect()->route('admin.settings.security.edit')
            ->with('success', $request->boolean('staff_two_factor_required')
                ? 'Autentikasi dua langkah sekarang wajib untuk seluruh staf.'
                : 'Kewajiban autentikasi dua langkah untuk staf dinonaktifkan.');
    }
}
