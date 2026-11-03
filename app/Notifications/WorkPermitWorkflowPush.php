<?php

namespace App\Notifications;

use App\Models\WorkPermit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class WorkPermitWorkflowPush extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public WorkPermit $permit,
        public string $title,
        public string $body,
        public ?string $workspaceDivision = null,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon(asset('pwa/icon-192.png'))
            ->badge(asset('pwa/badge-96.png'))
            ->lang('id')
            ->tag("{$this->permit->permit_kind}-permit-workflow-{$this->permit->id}-{$this->permit->status}")
            ->renotify()
            ->vibrate([180, 80, 180])
            ->data(['url' => route('staff.work-permits.show', $this->routeParameters($notifiable))])
            ->options(['TTL' => 3600, 'urgency' => 'high']);
    }

    /** @return array<string, string> */
    private function routeParameters(object $notifiable): array
    {
        $parameters = $notifiable->workPermitRouteParameters($this->permit);
        if ($this->workspaceDivision
            && $this->workspaceDivision !== $this->permit->assigned_division
            && $notifiable->canValidateDivision($this->workspaceDivision)) {
            $parameters['workspace'] = $this->workspaceDivision;
        }

        return $parameters;
    }
}
