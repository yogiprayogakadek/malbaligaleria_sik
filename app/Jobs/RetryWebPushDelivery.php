<?php

namespace App\Jobs;

use App\Models\WebPushMessageLog;
use App\Services\WebPushRetryPolicy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Minishlink\WebPush\Subscription as WebPushSubscription;
use Minishlink\WebPush\WebPush;
use NotificationChannels\WebPush\PushSubscription;
use RuntimeException;
use Throwable;

class RetryWebPushDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public int $messageLogId) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(WebPush $webPush): void
    {
        $log = WebPushMessageLog::query()->with('user')->find($this->messageLogId);
        if (! $log || $log->status === 'sent' || ! $log->payload) {
            return;
        }

        if ($log->user && ! $log->user->is_active) {
            $this->finish($log, 'skipped', 'Akun penerima sudah tidak aktif.');

            return;
        }

        $subscription = PushSubscription::query()->find($log->subscription_id);
        if (! $subscription || ! hash_equals($log->endpoint_hash, hash('sha256', $subscription->endpoint))) {
            $this->finish($log, 'expired', 'Subscription perangkat sudah tidak tersedia.');

            return;
        }

        $log->increment('attempts');
        $log->update(['status' => 'processing']);
        $report = $webPush->sendOneNotification(
            new WebPushSubscription(
                $subscription->endpoint,
                $subscription->public_key,
                $subscription->auth_token,
                $subscription->content_encoding,
            ),
            json_encode($log->payload, JSON_THROW_ON_ERROR),
            $log->options ?? [],
        );

        if ($report->isSuccess()) {
            $this->finish($log, 'sent', null, $report->getResponse()?->getStatusCode());

            return;
        }

        if ($report->isSubscriptionExpired()) {
            $subscription->delete();
            $this->finish($log, 'expired', $report->getReason(), $report->getResponse()?->getStatusCode());

            return;
        }

        if (! WebPushRetryPolicy::shouldRetry($report)) {
            $this->finish($log, 'failed', $report->getReason(), $report->getResponse()?->getStatusCode());

            return;
        }

        $log->update([
            'status' => 'retrying',
            'response_code' => $report->getResponse()?->getStatusCode(),
            'failure_reason' => mb_substr($report->getReason(), 0, 1000),
        ]);

        throw new RuntimeException('Layanan Web Push belum dapat menerima pesan.');
    }

    public function failed(?Throwable $exception): void
    {
        $log = WebPushMessageLog::query()->find($this->messageLogId);
        if ($log && $log->status !== 'sent') {
            $this->finish($log, 'failed', $log->failure_reason ?: 'Pengiriman gagal setelah tiga percobaan.');
        }
    }

    private function finish(WebPushMessageLog $log, string $status, ?string $reason, ?int $responseCode = null): void
    {
        $log->update([
            'status' => $status,
            'response_code' => $responseCode,
            'failure_reason' => $reason ? mb_substr($reason, 0, 1000) : null,
            'payload' => null,
            'options' => null,
            'sent_at' => $status === 'sent' ? now() : null,
            'failed_at' => in_array($status, ['failed', 'expired'], true) ? now() : null,
        ]);
    }
}
