<?php

namespace App\Notifications;

use App\Models\LoadingPermit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class LoadingPermitSubmittedPush extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public LoadingPermit $permit)
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
            ->title('Permohonan loading baru')
            ->body("{$this->permit->tenant_name} mengajukan {$this->permit->direction_label}.")
            ->icon(asset('pwa/icon-192.png'))
            ->badge(asset('pwa/badge-96.png'))
            ->lang('id')
            ->tag("loading-permit-{$this->permit->id}")
            ->renotify()
            ->vibrate([180, 80, 180])
            ->data([
                'url' => route('tr.show', $this->permit->permit_number),
            ])
            ->options([
                'TTL' => 3600,
                'urgency' => 'high',
            ]);
    }
}
