<?php

namespace App\Listeners;

use App\Jobs\RetryWebPushDelivery;
use App\Models\User;
use App\Models\WebPushMessageLog;
use App\Services\WebPushRetryPolicy;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\Events\NotificationSent;

class RecordWebPushDelivery
{
    public function handleSent(NotificationSent $event): void
    {
        $this->record($event, 'sent');
    }

    public function handleFailed(NotificationFailed $event): void
    {
        $status = match (true) {
            $event->report->isSubscriptionExpired() => 'expired',
            WebPushRetryPolicy::shouldRetry($event->report) => 'retrying',
            default => 'failed',
        };
        $log = $this->record($event, $status);

        if ($status === 'retrying') {
            RetryWebPushDelivery::dispatch($log->id)->delay(now()->addMinute());
        }
    }

    private function record(NotificationSent|NotificationFailed $event, string $status): WebPushMessageLog
    {
        $message = $event->message->toArray();
        [$permitType, $permitId] = $this->permitReference((string) ($message['tag'] ?? ''));
        $retrying = $status === 'retrying';

        return WebPushMessageLog::query()->create([
            'user_id' => $event->subscription->subscribable_type === User::class
                ? $event->subscription->subscribable_id
                : null,
            'subscription_id' => $event->subscription->getKey(),
            'endpoint_hash' => hash('sha256', $event->report->getEndpoint()),
            'title' => mb_substr((string) ($message['title'] ?? 'Notifikasi perangkat'), 0, 255),
            'tag' => mb_substr((string) ($message['tag'] ?? ''), 0, 255) ?: null,
            'permit_type' => $permitType,
            'permit_id' => $permitId,
            'status' => $status,
            'response_code' => $event->report->getResponse()?->getStatusCode(),
            'failure_reason' => $status === 'sent' ? null : mb_substr($event->report->getReason(), 0, 1000),
            'payload' => $retrying ? $message : null,
            'options' => $retrying ? $event->message->getOptions() : null,
            'sent_at' => $status === 'sent' ? now() : null,
            'failed_at' => in_array($status, ['failed', 'expired'], true) ? now() : null,
        ]);
    }

    /** @return array{0: string|null, 1: int|null} */
    private function permitReference(string $tag): array
    {
        if (preg_match('/^loading-permit-(\d+)$/', $tag, $matches)
            || preg_match('/^sla-overdue-loading-(\d+)-/', $tag, $matches)) {
            return ['loading', (int) $matches[1]];
        }

        if (preg_match('/^work-permit-(\d+)$/', $tag, $matches)
            || preg_match('/^(?:work|event)-permit-workflow-(\d+)-/', $tag, $matches)
            || preg_match('/^sla-overdue-work-(\d+)-/', $tag, $matches)) {
            return ['work', (int) $matches[1]];
        }

        return [null, null];
    }
}
