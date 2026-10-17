<?php

namespace App\Jobs;

use App\Mail\AnnouncementMail;
use App\Models\AnnouncementRecipient;
use App\Services\MailSettingsConfigurator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAnnouncementEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $recipientId) {}

    public function backoff(): array
    {
        return [60, 180, 600];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('announcement-email-send'))->releaseAfter(3)->expireAfter(120)];
    }

    public function handle(MailSettingsConfigurator $mailSettings): void
    {
        $recipient = AnnouncementRecipient::query()->with('campaign')->find($this->recipientId);
        if (! $recipient || $recipient->status === 'sent') {
            return;
        }
        if ($recipient->campaign?->cancelled_at) {
            $recipient->update(['status' => 'cancelled', 'error' => 'Kampanye dibatalkan oleh administrator.']);
            $recipient->campaign->refreshProgress();

            return;
        }

        $recipient->increment('attempts');
        $recipient->update(['status' => 'processing', 'error' => null]);

        try {
            $mailSettings->apply(true);
            Mail::to($recipient->destination)->send(new AnnouncementMail(
                $recipient->campaign->title,
                $recipient->campaign->message,
                $recipient->recipient_name,
            ));
            $recipient->update(['status' => 'sent', 'sent_at' => now()]);
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
            $recipient->update([
                'status' => 'failed',
                'error' => str($exception?->getMessage() ?: 'Pengiriman gagal setelah beberapa percobaan.')->limit(500),
            ]);
            $recipient->campaign?->refreshProgress();
        }
    }
}
