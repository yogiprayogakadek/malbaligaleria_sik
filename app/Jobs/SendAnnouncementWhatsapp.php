<?php

namespace App\Jobs;

use App\Models\AnnouncementRecipient;
use App\Models\WhatsappSetting;
use App\Services\WahaClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class SendAnnouncementWhatsapp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public int $recipientId) {}

    public function backoff(): array
    {
        return [60, 180, 600];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('announcement-whatsapp-send'))->releaseAfter(10)->expireAfter(90)];
    }

    public function handle(WahaClient $client): void
    {
        $recipient = AnnouncementRecipient::query()->with('campaign')->find($this->recipientId);
        if (! $recipient || $recipient->status === 'sent') {
            return;
        }
        if ($recipient->campaign?->cancelled_at) {
            $this->finish($recipient, 'cancelled', 'Kampanye dibatalkan oleh administrator.');

            return;
        }

        $setting = WhatsappSetting::current();
        if (! $setting?->is_active) {
            $this->finish($recipient, 'skipped', 'Integrasi WhatsApp tidak aktif.');

            return;
        }

        $recipient->increment('attempts');
        $recipient->update(['status' => 'processing', 'error' => null]);

        try {
            $message = implode("\n", [
                '*Mal Bali Galeria*',
                '*'.$recipient->campaign->title.'*',
                '',
                $recipient->campaign->message,
                '',
                'Pesan resmi Property Management Mal Bali Galeria.',
            ]);
            $result = $client->sendText($setting, $recipient->destination, $message);
            $providerId = data_get($result, 'id.id') ?? data_get($result, 'id') ?? data_get($result, 'key.id');
            $recipient->update([
                'status' => 'sent',
                'provider_message_id' => is_scalar($providerId) ? (string) $providerId : null,
                'sent_at' => now(),
            ]);
            $recipient->campaign->refreshProgress();
        } catch (Throwable $exception) {
            $recipient->update([
                'status' => $this->attempts() < $this->tries ? 'queued' : 'failed',
                'error' => str($exception->getMessage())->limit(500),
            ]);
            $recipient->campaign->refreshProgress();

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $recipient = AnnouncementRecipient::query()->with('campaign')->find($this->recipientId);
        if ($recipient && $recipient->status !== 'sent') {
            $this->finish($recipient, 'failed', $exception?->getMessage() ?: 'Pengiriman gagal setelah beberapa percobaan.');
        }
    }

    private function finish(AnnouncementRecipient $recipient, string $status, string $error): void
    {
        $recipient->update(['status' => $status, 'error' => str($error)->limit(500)]);
        $recipient->campaign?->refreshProgress();
    }
}
