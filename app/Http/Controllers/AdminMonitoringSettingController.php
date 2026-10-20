<?php

namespace App\Http\Controllers;

use App\Models\MonitoringSetting;
use App\Services\MonitoringWebhookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminMonitoringSettingController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'webhook_url' => ['nullable', 'url:https', 'max:1000'],
            'signing_secret' => ['nullable', 'string', 'min:32', 'max:255'],
            'timeout_seconds' => ['required', 'integer', 'between:3,20'],
            'current_password' => ['required', 'current_password'],
        ]);
        if ($request->boolean('enabled')) {
            $host = strtolower((string) parse_url((string) $data['webhook_url'], PHP_URL_HOST));
            if (! in_array($host, config('monitoring.allowed_hosts', []), true)) {
                throw ValidationException::withMessages(['webhook_url' => 'Hostname belum tercantum dalam MONITORING_ALLOWED_HOSTS.']);
            }
        }

        $current = MonitoringSetting::current();
        if ($request->boolean('enabled') && blank($data['signing_secret'] ?? null) && blank($current->signing_secret)) {
            throw ValidationException::withMessages(['signing_secret' => 'Signing secret wajib diisi saat monitoring pertama kali diaktifkan.']);
        }
        MonitoringSetting::query()->updateOrCreate(['id' => 1], [
            'enabled' => $request->boolean('enabled'),
            'webhook_url' => $data['webhook_url'],
            'signing_secret' => filled($data['signing_secret'] ?? null) ? $data['signing_secret'] : $current->signing_secret,
            'timeout_seconds' => $data['timeout_seconds'],
        ]);

        return back()->with('success', 'Konfigurasi monitoring eksternal diperbarui.');
    }

    public function test(Request $request, MonitoringWebhookService $webhook): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        try {
            $webhook->send('monitoring.test', ['message' => 'Pengujian webhook dari Sistem Perizinan MBG.']);
        } catch (Throwable $exception) {
            return back()->with('monitoring_error', 'Webhook gagal: '.str($exception->getMessage())->limit(180));
        }

        return back()->with('success', 'Webhook monitoring berhasil menerima pesan uji.');
    }
}
