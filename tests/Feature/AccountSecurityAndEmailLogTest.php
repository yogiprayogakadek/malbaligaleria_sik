<?php

namespace Tests\Feature;

use App\Models\EmailMessageLog;
use App\Models\LoadingPermit;
use App\Models\SystemAlert;
use App\Models\User;
use App\Notifications\LoadingPermitApplicantMail;
use App\Notifications\SystemHealthAlertPush;
use App\Services\SystemHealthMonitor;
use App\Services\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class AccountSecurityAndEmailLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_and_revoke_only_their_other_session(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['phone' => '081277700001']);
        $otherUser = User::factory()->create(['phone' => '081277700002']);
        $ownedSession = Str::random(40);
        $foreignSession = Str::random(40);

        $this->insertSession($ownedSession, $user->id, 'Mozilla/5.0 (Android) Chrome/120.0');
        $this->insertSession($foreignSession, $otherUser->id, 'Mozilla/5.0 (iPhone) Safari/605.1');

        $this->actingAs($user)->get(route('account.security.index'))
            ->assertOk()
            ->assertSee('Sesi aktif')
            ->assertSee('Android');

        $this->actingAs($user)->delete(route('account.security.sessions.destroy', $ownedSession), [
            'current_password' => 'password',
        ])->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => $ownedSession]);
        $this->assertDatabaseHas('sessions', ['id' => $foreignSession, 'user_id' => $otherUser->id]);
    }

    public function test_session_revocation_rejects_an_incorrect_password(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['phone' => '081277700003']);
        $sessionId = Str::random(40);
        $this->insertSession($sessionId, $user->id, 'Mozilla/5.0 Windows Chrome/120.0');

        $this->actingAs($user)->delete(route('account.security.sessions.destroy', $sessionId), [
            'current_password' => 'incorrect-password',
        ])->assertSessionHasErrors('current_password');

        $this->assertDatabaseHas('sessions', ['id' => $sessionId]);
    }

    public function test_guest_cannot_access_account_security(): void
    {
        $this->get(route('account.security.index'))->assertRedirect(route('login'));
    }

    public function test_applicant_email_delivery_events_are_logged_without_plain_recipient_storage(): void
    {
        $permit = $this->loadingPermit();
        $notifiable = (new AnonymousNotifiable)->route('mail', 'tenant@example.com');
        $sent = new LoadingPermitApplicantMail($permit, LoadingPermitApplicantMail::SUBMITTED);
        $sent->id = (string) Str::uuid();

        Event::dispatch(new NotificationSent($notifiable, $sent, 'mail'));

        $log = EmailMessageLog::query()->firstOrFail();
        $this->assertSame('sent', $log->status);
        $this->assertSame('tenant@example.com', $log->recipient);
        $this->assertSame(hash('sha256', 'tenant@example.com'), $log->recipient_hash);
        $this->assertStringNotContainsString('tenant@example.com', DB::table('email_message_logs')->value('recipient'));

        $failed = new LoadingPermitApplicantMail($permit, LoadingPermitApplicantMail::REJECTED);
        $failed->id = (string) Str::uuid();
        Event::dispatch(new NotificationFailed($notifiable, $failed, 'mail', [
            'exception' => new RuntimeException('SMTP unavailable'),
        ]));

        $this->assertDatabaseHas('email_message_logs', [
            'notification_id' => $failed->id,
            'status' => 'failed',
        ]);
    }

    public function test_only_admin_can_open_email_logs(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081277700004']);
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081277700005']);

        $this->actingAs($admin)->get(route('admin.email-logs.index'))->assertOk();
        $this->actingAs($tenant)->get(route('admin.email-logs.index'))->assertForbidden();
    }

    public function test_admin_can_retry_a_failed_applicant_email(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081277700007']);
        $permit = $this->loadingPermit();
        $log = EmailMessageLog::query()->create([
            'notification_id' => (string) Str::uuid(),
            'recipient' => 'tenant@example.com',
            'recipient_hash' => hash('sha256', 'tenant@example.com'),
            'notification_type' => LoadingPermitApplicantMail::class,
            'event_key' => LoadingPermitApplicantMail::SUBMITTED,
            'emailable_type' => LoadingPermit::class,
            'emailable_id' => $permit->id,
            'subject' => 'Permohonan loading diterima',
            'status' => 'failed',
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.email-logs.retry', $log))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('email_message_logs', [
            'id' => $log->id,
            'status' => 'retrying',
            'attempts' => 2,
        ]);
        Notification::assertSentOnDemand(LoadingPermitApplicantMail::class, function ($notification, $channels, $notifiable): bool {
            return $notifiable->routes['mail'] === 'tenant@example.com'
                && $notification->type === LoadingPermitApplicantMail::SUBMITTED;
        });
    }

    public function test_health_monitor_alerts_admin_once_and_resolves_recovered_component(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081277700006']);
        $health = \Mockery::mock(SystemHealthService::class);
        $health->shouldReceive('checks')->twice()->andReturn([
            ['name' => 'Database', 'status' => 'error', 'detail' => 'Database tidak dapat dihubungi.', 'lastSeen' => null],
        ]);

        $monitor = new SystemHealthMonitor($health);
        $monitor->run();
        $monitor->run();

        $this->assertSame(1, SystemAlert::query()->whereNull('resolved_at')->count());
        Notification::assertSentToTimes($admin, SystemHealthAlertPush::class, 1);

        $recovered = \Mockery::mock(SystemHealthService::class);
        $recovered->shouldReceive('checks')->once()->andReturn([
            ['name' => 'Database', 'status' => 'healthy', 'detail' => 'Koneksi database aktif.', 'lastSeen' => null],
        ]);
        (new SystemHealthMonitor($recovered))->run();

        $this->assertSame(0, SystemAlert::query()->whereNull('resolved_at')->count());
        $this->assertSame(1, SystemAlert::query()->whereNotNull('resolved_at')->count());
    }

    private function insertSession(string $id, int $userId, string $userAgent): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '192.168.0.10',
            'user_agent' => $userAgent,
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);
    }

    private function loadingPermit(): LoadingPermit
    {
        return LoadingPermit::query()->create([
            'permit_number' => 'MBG/SIK/X/0901',
            'tenant_name' => 'Tenant Test',
            'applicant_name' => 'Pemohon Test',
            'applicant_phone' => '081200000099',
            'applicant_email' => 'tenant@example.com',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'movement_time' => '22:00',
            'item_count' => 1,
            'item_unit' => 'unit',
            'item_description' => 'Barang uji',
            'id_doc_path' => 'private/test.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'pending',
        ]);
    }
}
