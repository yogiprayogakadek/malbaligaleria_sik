<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSecuritySettingRequest;
use App\Models\SecuritySetting;
use App\Models\StaffLoginEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminSecuritySettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.security', [
            'setting' => SecuritySetting::current(),
            'loginEvents' => StaffLoginEvent::query()->with('user')->latest('occurred_at')->limit(25)->get(),
        ]);
    }

    public function update(UpdateSecuritySettingRequest $request): RedirectResponse
    {
        $current = SecuritySetting::current();
        SecuritySetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'staff_two_factor_required' => $request->boolean('staff_two_factor_required'),
                'login_alerts_enabled' => $request->boolean('login_alerts_enabled'),
                'failed_login_alert_threshold' => $request->integer('failed_login_alert_threshold'),
                'sensitive_actions_require_2fa' => $request->has('sensitive_actions_require_2fa')
                    ? $request->boolean('sensitive_actions_require_2fa')
                    : $current->sensitive_actions_require_2fa,
            ],
        );

        $request->session()->forget(['two_factor.user_id', 'two_factor.remember', 'sensitive_action.confirmed_at']);

        return redirect()->route('admin.settings.security.edit')
            ->with('success', 'Kebijakan keamanan login berhasil diperbarui.');
    }
}
