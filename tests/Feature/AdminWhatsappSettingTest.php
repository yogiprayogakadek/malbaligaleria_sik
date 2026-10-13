<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsappMessage;
use App\Models\LoadingPermit;
use App\Models\User;
use App\Models\WhatsappMessageLog;
use App\Models\WhatsappSetting;
use App\Models\WorkPermit;
use App\Services\WahaClient;
use App\Services\WhatsappNotificationDispatcher;
use App\Support\IndonesianPhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminWhatsappSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['waha.allowed_hosts' => ['192.168.0.253']]);
    }

    public function test_only_admin_can_manage_whatsapp_settings(): void
    {
        $this->actingAs($this->user('admin'))
            ->get(route('admin.settings.whatsapp.edit'))
            ->assertOk();

        $this->actingAs($this->user('tenant'))
            ->get(route('admin.settings.whatsapp.edit'))
            ->assertForbidden();
    }

    public function test_admin_can_store_an_encrypted_api_key_for_an_allowlisted_host(): void
    {
        $apiKey = 'waha-private-api-key-123456';

        $this->actingAs($this->user('admin'))
            ->put(route('admin.settings.whatsapp.update'), $this->validSetting(['api_key' => $apiKey]))
            ->assertSessionHasNoErrors();

        $setting = WhatsappSetting::current();
        $this->assertSame($apiKey, $setting->api_key);
        $this->assertNotSame($apiKey, DB::table('whatsapp_settings')->value('api_key'));

        $this->actingAs($this->user('admin'))
            ->get(route('admin.settings.whatsapp.edit'))
            ->assertDontSee($apiKey);
    }

    public function test_admin_cannot_point_waha_at_a_host_outside_the_allowlist(): void
    {
        $this->actingAs($this->user('admin'))
            ->put(route('admin.settings.whatsapp.update'), $this->validSetting([
                'base_url' => 'http://127.0.0.1:3000',
            ]))
            ->assertSessionHasErrors('base_url');

        $this->assertDatabaseCount('whatsapp_settings', 0);
    }

    public function test_admin_can_select_active_staff_recipients(): void
    {
        $admin = $this->user('admin');
        $validator = $this->user('validator', ['division' => 'TR']);
        $inactive = $this->user('secretary', ['is_active' => false]);

        $this->actingAs($admin)
            ->put(route('admin.settings.whatsapp.recipients'), [
                'recipients' => [$admin->id, $validator->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($admin->refresh()->whatsapp_notifications_enabled);
        $this->assertTrue($validator->refresh()->whatsapp_notifications_enabled);
        $this->assertFalse($inactive->refresh()->whatsapp_notifications_enabled);
    }

    public function test_test_message_checks_the_session_and_uses_the_api_key_header(): void
    {
        Http::fake([
            'http://192.168.0.253:3000/api/sessions/default' => Http::response(['status' => 'WORKING']),
            'http://192.168.0.253:3000/api/sendText' => Http::response(['id' => ['id' => 'message-1']]),
        ]);
        WhatsappSetting::create($this->validSetting());

        $this->actingAs($this->user('admin'))
            ->post(route('admin.settings.whatsapp.test'), ['recipient' => '081234567890'])
            ->assertSessionHas('whatsapp_test_success');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://192.168.0.253:3000/api/sendText'
            && $request->hasHeader('X-Api-Key', 'waha-private-api-key-123456')
            && $request['chatId'] === '6281234567890@c.us'
            && $request['session'] === 'default'
        );
    }

    public function test_loading_notification_is_queued_once_for_applicant_and_selected_relevant_staff(): void
    {
        Queue::fake();
        WhatsappSetting::create($this->validSetting());
        $this->user('validator', [
            'division' => 'TR',
            'whatsapp_notifications_enabled' => true,
        ]);
        $this->user('validator', [
            'division' => 'MEP',
            'whatsapp_notifications_enabled' => true,
        ]);
        $permit = $this->loadingPermit();
        $dispatcher = app(WhatsappNotificationDispatcher::class);

        $dispatcher->loading($permit, 'submitted', 'Permohonan Baru', 'Permohonan masuk ke antrean TR.');
        $dispatcher->loading($permit, 'submitted', 'Permohonan Baru', 'Permohonan masuk ke antrean TR.');

        $this->assertDatabaseCount('whatsapp_message_logs', 2);
        Queue::assertPushed(SendWhatsappMessage::class, 2);
        $this->assertDatabaseMissing('whatsapp_message_logs', ['recipient_name' => null]);
    }

    public function test_queued_job_sends_the_message_and_marks_the_audit_log_as_sent(): void
    {
        Http::fake([
            'http://192.168.0.253:3000/api/sendText' => Http::response(['id' => ['id' => 'message-99']]),
        ]);
        WhatsappSetting::create($this->validSetting());
        $log = WhatsappMessageLog::create([
            'unique_key' => hash('sha256', 'test-message'),
            'permit_type' => 'loading',
            'permit_id' => 1,
            'event' => 'test',
            'recipient_kind' => 'applicant',
            'recipient_name' => 'Pemohon Test',
            'recipient_phone' => '6281234567890',
            'recipient_phone_hash' => hash('sha256', '6281234567890'),
            'message' => 'Pesan pengujian',
            'status' => 'queued',
        ]);

        (new SendWhatsappMessage($log->id))->handle(app(WahaClient::class));

        $log->refresh();
        $this->assertSame('sent', $log->status);
        $this->assertSame('message-99', $log->provider_message_id);
        $this->assertNotNull($log->sent_at);
    }

    public function test_work_permit_notification_only_queues_selected_relevant_staff_and_the_applicant(): void
    {
        Queue::fake();
        WhatsappSetting::create($this->validSetting());
        $mep = $this->user('validator', [
            'division' => 'MEP',
            'whatsapp_notifications_enabled' => true,
        ]);
        $tr = $this->user('validator', [
            'division' => 'TR',
            'whatsapp_notifications_enabled' => true,
        ]);
        $permit = WorkPermit::create([
            'contractor_name' => 'Kontraktor Test',
            'applicant_name' => 'Pemohon Test',
            'applicant_phone' => '081234567890',
            'work_location' => 'Unit Lantai 1',
            'work_category' => 'other',
            'assigned_division' => 'MEP',
            'work_type' => 'Perbaikan instalasi',
            'work_schedule' => 'inside_store',
            'work_schedules' => ['inside_store'],
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'id_doc_path' => 'permits/work/id-docs/test.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'mep_review',
        ]);

        app(WhatsappNotificationDispatcher::class)->work(
            $permit,
            'status_mep_review_1',
            'Permohonan Izin Kerja Baru',
            'Permohonan masuk ke antrean MEP.',
        );

        $this->assertDatabaseHas('whatsapp_message_logs', ['user_id' => $mep->id]);
        $this->assertDatabaseMissing('whatsapp_message_logs', ['user_id' => $tr->id]);
        $this->assertDatabaseCount('whatsapp_message_logs', 2);
        Queue::assertPushed(SendWhatsappMessage::class, 2);
    }

    public function test_indonesian_phone_numbers_are_normalized_strictly(): void
    {
        $this->assertSame('6281234567890', IndonesianPhoneNumber::normalize('+62 812-3456-7890'));
        $this->assertSame('6281234567890', IndonesianPhoneNumber::normalize('081234567890'));
        $this->assertNull(IndonesianPhoneNumber::normalize('123'));
    }

    private function validSetting(array $overrides = []): array
    {
        return array_merge([
            'base_url' => 'http://192.168.0.253:3000',
            'api_key' => 'waha-private-api-key-123456',
            'session' => 'default',
            'timeout' => 10,
            'is_active' => true,
            'notify_applicants' => true,
        ], $overrides);
    }

    private function user(string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'phone' => fake()->unique()->numerify('0812########'),
            'role' => $role,
            'tenant_name' => $role === 'tenant' ? 'Tenant Test' : null,
        ], $overrides));
    }

    private function loadingPermit(): LoadingPermit
    {
        return LoadingPermit::create([
            'permit_number' => 'MBG/SIK/X/9001',
            'tenant_name' => 'Tenant Test',
            'applicant_name' => 'Pemohon Test',
            'applicant_phone' => '081234567890',
            'applicant_email' => 'pemohon@example.test',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'movement_time' => '22:00',
            'item_count' => 2,
            'item_unit' => 'pcs',
            'item_description' => 'Peralatan toko',
            'id_doc_path' => 'permits/id-docs/test.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'pending',
        ]);
    }
}
