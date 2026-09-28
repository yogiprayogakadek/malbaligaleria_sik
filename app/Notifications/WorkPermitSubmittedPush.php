<?php

namespace App\Notifications;

use App\Models\WorkPermit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class WorkPermitSubmittedPush extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WorkPermit $permit)
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Permohonan izin kerja baru')
            ->body("{$this->permit->contractor_name} mengajukan Surat Izin Kerja.")
            ->icon(asset('pwa/icon-192.png'))
            ->badge(asset('pwa/badge-96.png'))
            ->lang('id')
            ->tag("work-permit-{$this->permit->id}")
            ->renotify()
            ->vibrate([180, 80, 180])
            ->data([
                'url' => route('staff.work-permits.show', $this->permit->public_token),
            ])
            ->options([
                'TTL' => 3600,
                'urgency' => 'high',
            ]);
    }
}
