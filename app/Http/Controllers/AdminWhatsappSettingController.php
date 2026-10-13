<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendTestWhatsappRequest;
use App\Http\Requests\UpdateWhatsappRecipientsRequest;
use App\Http\Requests\UpdateWhatsappSettingRequest;
use App\Models\User;
use App\Models\WhatsappMessageLog;
use App\Models\WhatsappSetting;
use App\Services\WahaClient;
use App\Support\IndonesianPhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class AdminWhatsappSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.whatsapp', [
            'setting' => WhatsappSetting::current(),
            'recipients' => User::query()
                ->whereIn('role', ['admin', 'validator', 'secretary'])
                ->orderByDesc('is_active')
                ->orderBy('role')
                ->orderBy('division')
                ->orderBy('name')
                ->get(),
            'logs' => WhatsappMessageLog::query()->latest()->limit(10)->get(),
        ]);
    }

    public function update(UpdateWhatsappSettingRequest $request): RedirectResponse
    {
        $setting = WhatsappSetting::current() ?? new WhatsappSetting;
        $data = $request->safe()->except('api_key');

        if ($request->filled('api_key')) {
            $data['api_key'] = $request->validated('api_key');
        }

        $setting->fill($data)->save();

        return back()->with('success', 'Konfigurasi WhatsApp berhasil disimpan.');
    }

    public function updateRecipients(UpdateWhatsappRecipientsRequest $request): RedirectResponse
    {
        $ids = collect($request->validated('recipients', []))->map(fn ($id): int => (int) $id)->all();

        DB::transaction(function () use ($ids): void {
            User::query()
                ->whereIn('role', ['admin', 'validator', 'secretary'])
                ->update(['whatsapp_notifications_enabled' => false]);

            User::query()
                ->where('is_active', true)
                ->whereIn('role', ['admin', 'validator', 'secretary'])
                ->whereKey($ids)
                ->update(['whatsapp_notifications_enabled' => true]);
        });

        return back()->with('success', 'Penerima notifikasi WhatsApp berhasil diperbarui.');
    }

    public function test(SendTestWhatsappRequest $request, WahaClient $client): RedirectResponse
    {
        $setting = WhatsappSetting::current();

        if (! $setting?->is_active) {
            return back()->withInput()->with('whatsapp_test_error', 'Aktifkan dan simpan konfigurasi WAHA terlebih dahulu.');
        }

        try {
            $session = $client->sessionStatus($setting);
            if (($session['status'] ?? null) !== 'WORKING') {
                return back()->withInput()->with(
                    'whatsapp_test_error',
                    'Sesi WAHA belum siap. Status saat ini: '.($session['status'] ?? 'tidak diketahui').'.',
                );
            }

            $client->sendText(
                $setting,
                $request->validated('recipient'),
                "*Mal Bali Galeria*\nPengujian WhatsApp berhasil.\n\nDikirim ".now()->format('d M Y, H:i').' WITA.',
            );
        } catch (Throwable $exception) {
            Log::warning('Pengujian WAHA dari admin gagal.', [
                'admin_id' => $request->user()->id,
                'recipient_hash' => hash('sha256', (string) IndonesianPhoneNumber::normalize($request->validated('recipient'))),
                'exception' => $exception->getMessage(),
            ]);

            return back()->withInput()->with(
                'whatsapp_test_error',
                'Pesan uji gagal dikirim. Periksa alamat server, API key, status sesi, firewall, dan log aplikasi.',
            );
        }

        return back()->with(
            'whatsapp_test_success',
            'Pesan uji berhasil dikirim ke '.IndonesianPhoneNumber::mask($request->validated('recipient')).'.',
        );
    }
}
