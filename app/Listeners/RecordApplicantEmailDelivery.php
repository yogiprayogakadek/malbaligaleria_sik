<?php

namespace App\Listeners;

use App\Models\EmailMessageLog;
use App\Notifications\LoadingPermitApplicantMail;
use App\Notifications\WorkPermitApplicantMail;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Throwable;

class RecordApplicantEmailDelivery
{
    public function handleSent(NotificationSent $event): void
    {
        if ($event->channel !== 'mail' || ! $this->supported($event->notification)) {
            return;
        }

        $this->record($event->notifiable, $event->notification, 'sent');
    }

    public function handleFailed(NotificationFailed $event): void
    {
        if ($event->channel !== 'mail' || ! $this->supported($event->notification)) {
            return;
        }

        $exception = $event->data['exception'] ?? null;
        $reason = $exception instanceof Throwable
            ? class_basename($exception).': '.$exception->getMessage()
            : 'Kanal email melaporkan kegagalan tanpa detail.';

        $this->record($event->notifiable, $event->notification, 'failed', mb_substr($reason, 0, 1000));
    }

    private function record(object $notifiable, object $notification, string $status, ?string $reason = null): void
    {
        $recipient = $this->recipient($notifiable);
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $permit = $notification->permit;
        $attributes = [
            'recipient' => strtolower($recipient),
            'recipient_hash' => hash('sha256', strtolower($recipient)),
            'notification_type' => $notification::class,
            'event_key' => $notification instanceof LoadingPermitApplicantMail ? $notification->type : 'status_update',
            'emailable_type' => $permit::class,
            'emailable_id' => $permit->getKey(),
            'subject' => $notification instanceof LoadingPermitApplicantMail
                ? match ($notification->type) {
                    LoadingPermitApplicantMail::APPROVED => 'Permohonan loading disetujui',
                    LoadingPermitApplicantMail::REJECTED => 'Perubahan status permohonan loading',
                    default => 'Permohonan loading diterima',
                }
                : $notification->messageTitle,
            'status' => $status,
            'failure_reason' => $reason,
            'sent_at' => $status === 'sent' ? now() : null,
            'failed_at' => $status === 'failed' ? now() : null,
        ];

        $existing = filled($notification->id ?? null)
            ? EmailMessageLog::query()->where('notification_id', $notification->id)->first()
            : null;

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        EmailMessageLog::query()->create(['notification_id' => $notification->id ?? null] + $attributes);
    }

    private function recipient(object $notifiable): string
    {
        $route = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('mail')
            : null;

        if (is_array($route)) {
            return (string) array_key_first($route);
        }

        return (string) $route;
    }

    private function supported(object $notification): bool
    {
        return $notification instanceof LoadingPermitApplicantMail
            || $notification instanceof WorkPermitApplicantMail;
    }
}
