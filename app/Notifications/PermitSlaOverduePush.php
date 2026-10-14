<?php

namespace App\Notifications;

use App\Models\LoadingPermit;
use App\Models\WorkPermit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PermitSlaOverduePush extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public LoadingPermit|WorkPermit $permit,
        public string $title,
        public string $body,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $isLoading = $this->permit instanceof LoadingPermit;
        $targetUrl = $isLoading
            ? ($notifiable->isAdmin()
                ? route('admin.loading.show', $this->permit->permit_number)
                : route('tr.show', $this->permit->permit_number))
            : route('staff.work-permits.show', $this->permit->public_token);

        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon(asset('pwa/icon-192.png'))
            ->badge(asset('pwa/badge-96.png'))
            ->lang('id')
            ->tag('sla-overdue-'.($isLoading ? 'loading' : 'work')."-{$this->permit->id}-{$this->permit->status}")
            ->renotify()
            ->vibrate([180, 80, 180])
            ->data(['url' => $targetUrl])
            ->options(['TTL' => 3600, 'urgency' => 'high']);
    }
}
