<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMailSettingRequest;
use App\Models\MailSetting;
use App\Services\MailSettingsConfigurator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminMailSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.mail', [
            'setting' => MailSetting::current(),
        ]);
    }

    public function update(UpdateMailSettingRequest $request, MailSettingsConfigurator $configurator): RedirectResponse
    {
        $setting = MailSetting::current() ?? new MailSetting;
        $data = $request->safe()->except('password');

        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        $setting->fill($data)->save();
        $configurator->apply(true);

        return redirect()->route('admin.settings.mail.edit')
            ->with('success', 'Konfigurasi email berhasil disimpan.');
    }
}
