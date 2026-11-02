<?php

namespace Tests\Feature;

use App\Jobs\RetryWebPushDelivery;
use App\Models\User;
use App\Models\WebPushMessageLog;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use Mockery;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\Events\NotificationSent;
use NotificationChannels\WebPush\WebPushMessage;
use Tests\TestCase;

class WebPushDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_delivery_is_logged_without_endpoint_or_payload(): void
    {
        [$user, $subscription, $endpoint] = $this->subscription();
        $report = $this->report($endpoint, 201, true, 'Created');

        event(new NotificationSent($report, $subscription, $this->message()));

        $log = WebPushMessageLog::query()->sole();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('sent', $log->status);
        $this->assertSame(hash('sha256', $endpoint), $log->endpoint_hash);
        $this->assertSame('loading', $log->permit_type);
        $this->assertSame(42, $log->permit_id);
        $this->assertNull($log->payload);
        $this->assertStringNotContainsString($endpoint, json_encode($log->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function test_temporary_failure_is_encrypted_and_queued_for_retry(): void
    {
        Queue::fake();
        [, $subscription, $endpoint] = $this->subscription();
        $report = $this->report($endpoint, 503, false, 'Service unavailable');

        event(new NotificationFailed($report, $subscription, $this->message()));

        $log = WebPushMessageLog::query()->sole();
        $this->assertSame('retrying', $log->status);
        $this->assertSame('Permohonan loading baru', $log->payload['title']);
        $this->assertStringNotContainsString('/tr/secret-token', (string) DB::table('web_push_message_logs')->value('payload'));
        Queue::assertPushed(RetryWebPushDelivery::class, fn (RetryWebPushDelivery $job): bool => $job->messageLogId === $log->id);
    }

    public function test_permanent_failure_is_not_retried(): void
    {
        Queue::fake();
        [, $subscription, $endpoint] = $this->subscription();

        event(new NotificationFailed(
            $this->report($endpoint, 400, false, 'Bad request'),
            $subscription,
            $this->message(),
        ));

        $this->assertDatabaseHas('web_push_message_logs', ['status' => 'failed', 'response_code' => 400]);
        Queue::assertNothingPushed();
    }

    public function test_retry_job_marks_delivery_as_sent_and_removes_sensitive_payload(): void
    {
        [, $subscription, $endpoint] = $this->subscription();
        $log = WebPushMessageLog::query()->create([
            'subscription_id' => $subscription->id,
            'endpoint_hash' => hash('sha256', $endpoint),
            'title' => 'Pengingat antrean',
            'status' => 'retrying',
            'payload' => $this->message()->toArray(),
            'options' => $this->message()->getOptions(),
        ]);
        $webPush = Mockery::mock(WebPush::class);
        $webPush->shouldReceive('sendOneNotification')
            ->once()
            ->andReturn($this->report($endpoint, 201, true, 'Created'));

        (new RetryWebPushDelivery($log->id))->handle($webPush);

        $log->refresh();
        $this->assertSame('sent', $log->status);
        $this->assertSame(2, $log->attempts);
        $this->assertNull($log->payload);
        $this->assertNull($log->options);
        $this->assertNotNull($log->sent_at);
    }

    private function subscription(): array
    {
        $user = User::factory()->create([
            'role' => 'validator',
            'division' => 'TR',
            'phone' => fake()->unique()->numerify('08##########'),
        ]);
        $endpoint = 'https://push.example.test/device-'.fake()->uuid();
        $subscription = $user->updatePushSubscription(
            $endpoint,
            str_repeat('A', 87),
            str_repeat('B', 22),
            'aes128gcm',
        );

        return [$user, $subscription, $endpoint];
    }

    private function message(): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Permohonan loading baru')
            ->body('Permohonan menunggu pemeriksaan.')
            ->tag('loading-permit-42')
            ->data(['url' => 'https://sik.example.test/tr/secret-token'])
            ->options(['TTL' => 3600, 'urgency' => 'high']);
    }

    private function report(string $endpoint, int $status, bool $success, string $reason): MessageSentReport
    {
        return new MessageSentReport(
            new Request('POST', $endpoint),
            new Response($status),
            $success,
            $reason,
        );
    }
}
