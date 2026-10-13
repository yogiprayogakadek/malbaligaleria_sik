<?php

namespace App\Jobs;

use App\Models\WhatsappMessageLog;
use App\Models\WhatsappSetting;
use App\Services\WahaClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWhatsappMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public int $messageLogId) {}

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(WahaClient $client): void
    {
        $log = WhatsappMessageLog::find($this->messageLogId);
        $setting = WhatsappSetting::current();

        if (! $log || $log->status === 'sent') {
            return;
        }

        if (! $setting?->is_active) {
            $log->update(['status' => 'skipped', 'error' => 'Integrasi WhatsApp tidak aktif.']);

            return;
        }

        if ($log->recipient_kind === 'staff'
            && (! $log->user?->is_active || ! $log->user?->whatsapp_notifications_enabled)) {
            $log->update(['status' => 'skipped', 'error' => 'Penerima staf sudah tidak aktif.']);

            return;
        }

        $log->increment('attempts');
        $log->update(['status' => 'processing', 'error' => null]);

        try {
            $result = $client->sendText($setting, $log->recipient_phone, $log->message);
            $providerId = data_get($result, 'id.id') ?? data_get($result, 'id') ?? data_get($result, 'key.id');

            $log->update([
                'status' => 'sent',
                'provider_message_id' => is_scalar($providerId) ? (string) $providerId : null,
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'error' => str($exception->getMessage())->limit(500),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        WhatsappMessageLog::whereKey($this->messageLogId)->update([
            'status' => 'failed',
            'error' => str($exception?->getMessage() ?: 'Pengiriman gagal setelah beberapa percobaan.')->limit(500),
        ]);

        Log::warning('Pesan WhatsApp gagal dikirim.', [
            'whatsapp_message_log_id' => $this->messageLogId,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
