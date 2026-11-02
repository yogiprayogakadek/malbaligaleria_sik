<?php

namespace Tests\Feature;

use App\Jobs\RecordQueueHeartbeat;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WebPushMessageLog;
use App\Models\WhatsappSetting;
use App\Services\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use LogicException;
use Tests\TestCase;

class AdminAuditAndHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_changes_are_recorded_with_actor_and_without_secrets(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000001']);
        $this->actingAs($admin);

        $validator = User::factory()->create([
            'role' => 'validator',
            'division' => 'TR',
            'is_active' => true,
            'phone' => '081200000002',
        ]);
        $validator->update(['is_active' => false]);

        WhatsappSetting::query()->create([
            'base_url' => 'http://192.168.0.253:3000',
            'api_key' => 'plain-secret-key',
            'session' => 'default',
            'timeout' => 10,
            'is_active' => true,
            'notify_applicants' => true,
        ]);

        $userLog = AuditLog::query()
            ->where('auditable_type', User::class)
            ->where('auditable_id', $validator->id)
            ->where('event', 'updated')
            ->firstOrFail();

        $this->assertSame($admin->id, $userLog->actor_id);
        $this->assertTrue($userLog->old_values['is_active']);
        $this->assertFalse($userLog->new_values['is_active']);

        $settingLog = AuditLog::query()
            ->where('auditable_type', WhatsappSetting::class)
            ->where('event', 'created')
            ->firstOrFail();

        $this->assertSame('[dilindungi]', $settingLog->new_values['api_key']);
        $this->assertStringNotContainsString('plain-secret-key', json_encode($settingLog->new_values));
    }

    public function test_audit_records_cannot_be_changed_or_deleted_through_eloquent(): void
    {
        $log = AuditLog::query()->create([
            'event' => 'created',
            'new_values' => ['status' => 'pending'],
        ]);

        try {
            $log->update(['event' => 'updated']);
            $this->fail('Audit log seharusnya tidak dapat diubah.');
        } catch (LogicException $exception) {
            $this->assertSame('Audit log tidak dapat diubah.', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_only_admin_can_open_audit_and_health_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000003']);
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081200000004']);

        $this->actingAs($admin)->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('Riwayat perubahan sistem');
        $this->actingAs($admin)->get(route('admin.health.index'))
            ->assertOk()
            ->assertSee('Status layanan')
            ->assertSee('Otomasi SLA')
            ->assertSee('Web Push')
            ->assertDontSee('plain-secret-key');

        $this->actingAs($tenant)->get(route('admin.audit.index'))->assertForbidden();
        $this->actingAs($tenant)->get(route('admin.health.index'))->assertForbidden();
    }

    public function test_queue_heartbeat_is_written_only_after_the_job_runs(): void
    {
        Cache::forget('system-health:queue-worker');

        (new RecordQueueHeartbeat)->handle();

        $this->assertNotNull(Cache::get('system-health:queue-worker'));
    }

    public function test_external_operational_health_requires_token_and_fresh_heartbeats(): void
    {
        config()->set('monitoring.health_token', str_repeat('h', 40));
        Cache::put('system-health:scheduler', now()->toIso8601String(), now()->addMinutes(10));
        Cache::put('system-health:queue-worker', now()->toIso8601String(), now()->addMinutes(10));
        Cache::put('system-health:permit-sla-monitor', now()->toIso8601String(), now()->addMinutes(15));

        $this->getJson(route('health.operations'))->assertNotFound();
        $this->withToken(str_repeat('x', 40))->getJson(route('health.operations'))->assertNotFound();
        $this->withToken(str_repeat('h', 40))->getJson(route('health.operations'))
            ->assertOk()
            ->assertJson(['status' => 'ok', 'scheduler' => 'ok', 'queue' => 'ok', 'sla_monitor' => 'ok']);

        Cache::put('system-health:scheduler', now()->subMinutes(4)->toIso8601String(), now()->addMinutes(10));
        $this->withToken(str_repeat('h', 40))->getJson(route('health.operations'))
            ->assertStatus(503)
            ->assertJson(['status' => 'error', 'scheduler' => 'stale']);

        Cache::put('system-health:scheduler', now()->toIso8601String(), now()->addMinutes(10));
        Cache::put('system-health:permit-sla-monitor', now()->subMinutes(9)->toIso8601String(), now()->addMinutes(15));
        $this->withToken(str_repeat('h', 40))->getJson(route('health.operations'))
            ->assertStatus(503)
            ->assertJson(['status' => 'error', 'sla_monitor' => 'stale']);
    }

    public function test_reverb_origins_do_not_default_to_a_wildcard(): void
    {
        $this->assertNotContains('*', config('reverb.apps.apps.0.allowed_origins'));
    }

    public function test_system_health_warns_when_web_push_failed_recently(): void
    {
        config()->set('webpush.vapid', [
            'subject' => 'mailto:admin@example.test',
            'public_key' => 'public-key',
            'private_key' => 'private-key',
            'pem_file' => null,
        ]);
        WebPushMessageLog::query()->create([
            'endpoint_hash' => hash('sha256', 'device'),
            'status' => 'failed',
            'failed_at' => now(),
        ]);

        $check = collect(app(SystemHealthService::class)->checks())->firstWhere('name', 'Web Push');

        $this->assertSame('warning', $check['status']);
        $this->assertStringContainsString('1 pengiriman gagal', $check['detail']);
    }
}
