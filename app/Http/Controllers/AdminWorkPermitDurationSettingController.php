<?php

namespace App\Http\Controllers;

use App\Models\WorkPermitDurationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminWorkPermitDurationSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.work-permit-durations', [
            'setting' => WorkPermitDurationSetting::current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'default_days' => ['required', 'integer', 'between:1,30'],
            'fit_out_days' => ['required', 'integer', 'between:1,90'],
            'demolition_days' => ['required', 'integer', 'between:1,30'],
            'current_password' => ['required', 'current_password'],
        ], [
            'current_password.current_password' => 'Kata sandi admin tidak sesuai.',
        ]);

        unset($data['current_password']);
        WorkPermitDurationSetting::current()->update($data);

        return back()->with('success', 'Batas durasi izin kerja berhasil diperbarui.');
    }
}
