<?php

namespace App\Notifications;

use App\Models\SystemAlert;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class SystemHealthAlertPush extends Notification
{
    public function __construct(public SystemAlert $alert) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title("Gangguan sistem: {$this->alert->component}")
            ->body($this->alert->detail)
            ->icon(asset('pwa/icon-192.png'))
            ->badge(asset('pwa/badge-96.png'))
            ->lang('id')
            ->tag('system-health-'.(string) str($this->alert->component)->slug())
            ->renotify()
            ->vibrate([180, 80, 180])
            ->data(['url' => route('admin.health.index')])
            ->options(['TTL' => 1800, 'urgency' => 'high']);
    }
}
