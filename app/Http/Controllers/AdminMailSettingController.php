<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendTestMailRequest;
use App\Http\Requests\UpdateMailSettingRequest;
use App\Mail\MailConfigurationTest;
use App\Models\MailSetting;
use App\Services\MailSettingsConfigurator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

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

    public function test(SendTestMailRequest $request, MailSettingsConfigurator $configurator): RedirectResponse
    {
        $configurator->apply(true);

        if (config('mail.default') !== 'smtp') {
            return back()->withInput()->with(
                'mail_test_error',
                'Pengujian tidak dikirim karena mailer yang aktif bukan SMTP. Aktifkan konfigurasi SMTP lalu simpan kembali.',
            );
        }

        try {
            Mail::to($request->validated('recipient'))->send(new MailConfigurationTest(
                now()->timezone(config('app.timezone'))->format('d M Y, H:i:s'),
            ));
        } catch (Throwable $exception) {
            Log::warning('Pengujian konfigurasi email admin gagal.', [
                'admin_id' => $request->user()->id,
                'exception' => $exception->getMessage(),
            ]);

            return back()->withInput()->with(
                'mail_test_error',
                'Email uji gagal dikirim. Periksa server, port, keamanan koneksi, akun, kata sandi, dan log aplikasi.',
            );
        }

        return back()->with(
            'mail_test_success',
            'Email uji berhasil dikirim ke '.$request->validated('recipient').'. Periksa kotak masuk dan folder spam.',
        );
    }
}
