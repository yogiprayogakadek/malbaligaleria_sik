<?php

namespace Tests\Feature;

use App\Jobs\SendAnnouncementEmail;
use App\Jobs\SendAnnouncementWhatsapp;
use App\Mail\AnnouncementMail;
use App\Models\AnnouncementCampaign;
use App\Models\AnnouncementRecipient;
use App\Models\LoadingPermit;
use App\Models\User;
use App\Models\WhatsappSetting;
use App\Models\WorkPermit;
use App\Services\MailSettingsConfigurator;
use App\Services\WahaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_and_create_announcement_campaigns(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081200001001']);

        $this->actingAs($tenant)->get(route('admin.announcements.index'))->assertForbidden();
        $this->actingAs($tenant)->post(route('admin.announcements.store'), [])->assertForbidden();
    }

    public function test_admin_can_queue_deduplicated_whatsapp_and_email_announcements(): void
    {
        Queue::fake();
        $admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin MBG',
            'email' => 'admin-announcement@example.test',
            'phone' => '081200001010',
        ]);
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'name' => 'Tenant Satu',
            'email' => 'tenant-announcement@example.test',
            'phone' => '081200001011',
        ]);
        WhatsappSetting::query()->create($this->whatsappSetting());
        $this->loadingPermit($tenant->phone, $tenant->email);
        $this->workPermit('081200001012', 'applicant-announcement@example.test');
        $token = (string) Str::uuid();

        $payload = [
            'submission_token' => $token,
            'title' => 'Perubahan jam operasional',
            'message' => 'Jam operasional layanan berubah mulai hari Senin.',
            'channels' => ['whatsapp', 'email'],
            'audiences' => ['accounts', 'applicants'],
            'current_password' => 'password',
            'authorized_contact' => '1',
        ];

        $this->actingAs($admin)->post(route('admin.announcements.store'), $payload)
            ->assertRedirect(route('admin.announcements.index'))
            ->assertSessionHas('success');

        $campaign = AnnouncementCampaign::query()->sole();
        $this->assertSame(6, $campaign->recipient_count);
        $this->assertSame(6, $campaign->recipients()->count());
        $this->assertSame(3, $campaign->recipients()->where('channel', 'whatsapp')->count());
        $this->assertSame(3, $campaign->recipients()->where('channel', 'email')->count());
        Queue::assertPushed(SendAnnouncementWhatsapp::class, 3);
        Queue::assertPushed(SendAnnouncementEmail::class, 3);

        $stored = (string) DB::table('announcement_recipients')->value('destination');
        $this->assertStringNotContainsString('081200001010', $stored);
        $this->assertStringNotContainsString('@example.test', $stored);

        $this->actingAs($admin)->post(route('admin.announcements.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('announcement_campaigns', 1);
        $this->assertDatabaseCount('announcement_recipients', 6);
    }

    public function test_campaign_requires_password_confirmation_and_can_be_cancelled(): void
    {
        Queue::fake();
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin-cancel@example.test',
            'phone' => '081200001020',
        ]);

        $payload = [
            'submission_token' => (string) Str::uuid(),
            'title' => 'Informasi layanan',
            'message' => 'Informasi layanan untuk seluruh akun aktif.',
            'channels' => ['email'],
            'audiences' => ['accounts'],
            'current_password' => 'wrong-password',
            'authorized_contact' => '1',
        ];

        $this->actingAs($admin)->post(route('admin.announcements.store'), $payload)
            ->assertSessionHasErrors('current_password');
        $this->assertDatabaseCount('announcement_campaigns', 0);

        $payload['current_password'] = 'password';
        $this->actingAs($admin)->post(route('admin.announcements.store'), $payload)->assertRedirect();
        $campaign = AnnouncementCampaign::query()->sole();

        $this->actingAs($admin)->patch(route('admin.announcements.cancel', $campaign))->assertRedirect();
        $this->assertSame('cancelled', $campaign->fresh()->status);
        $this->assertSame(1, $campaign->fresh()->cancelled_count);
        $this->assertSame('cancelled', $campaign->recipients()->sole()->status);
    }

    public function test_notification_list_uses_a_clear_blue_unread_state(): void
    {
        $styles = file_get_contents(public_path('validator.css'));

        $this->assertStringContainsString('background: #e8f1fc', $styles);
        $this->assertStringContainsString('border-left-color: #2563eb', $styles);
        $this->assertStringNotContainsString('background: #fdf4f7', $styles);
    }

    public function test_whatsapp_and_email_jobs_record_success_without_exposing_destinations(): void
    {
        Mail::fake();
        Http::fake([
            'http://192.168.0.253:3000/api/sendText' => Http::response(['id' => ['id' => 'message-123']], 200),
        ]);
        WhatsappSetting::query()->create($this->whatsappSetting());
        $admin = User::factory()->create([
            'role' => 'admin',
            'phone' => '081200001030',
            'email' => 'job-admin@example.test',
        ]);
        $campaign = AnnouncementCampaign::query()->create([
            'created_by' => $admin->id,
            'submission_token_hash' => hash('sha256', (string) Str::uuid()),
            'title' => 'Informasi akses',
            'message' => 'Akses layanan akan ditutup sementara untuk pemeliharaan.',
            'channels' => ['whatsapp', 'email'],
            'audiences' => ['accounts'],
            'recipient_count' => 2,
        ]);
        $whatsapp = $this->campaignRecipient($campaign, 'whatsapp', '6281200001030');
        $email = $this->campaignRecipient($campaign, 'email', 'job-admin@example.test');

        (new SendAnnouncementWhatsapp($whatsapp->id))->handle(app(WahaClient::class));
        (new SendAnnouncementEmail($email->id))->handle(app(MailSettingsConfigurator::class));

        $this->assertSame('sent', $whatsapp->fresh()->status);
        $this->assertSame('message-123', $whatsapp->fresh()->provider_message_id);
        $this->assertSame('sent', $email->fresh()->status);
        $this->assertSame('completed', $campaign->fresh()->status);
        Mail::assertSent(AnnouncementMail::class, 1);
        Http::assertSentCount(1);
    }

    private function loadingPermit(string $phone, string $email): LoadingPermit
    {
        return LoadingPermit::query()->create([
            'permit_number' => 'MBG/SIK/ANN/0001',
            'tenant_name' => 'Tenant Pengujian',
            'applicant_name' => 'Pemohon Satu',
            'applicant_phone' => $phone,
            'applicant_email' => $email,
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'item_count' => 1,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian',
            'id_doc_path' => 'permits/test.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'pending',
        ]);
    }

    private function workPermit(string $phone, string $email): WorkPermit
    {
        return WorkPermit::query()->create([
            'contractor_name' => 'Kontraktor Pengujian',
            'applicant_name' => 'Pemohon Dua',
            'applicant_phone' => $phone,
            'applicant_email' => $email,
            'work_location' => 'Unit GF-10',
            'work_category' => 'other',
            'assigned_division' => 'MEP',
            'work_type' => 'Perawatan perangkat',
            'work_schedule' => 'inside_store',
            'work_schedules' => ['inside_store'],
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'needs_water' => false,
            'security_deposit' => false,
            'id_doc_type' => 'ktp',
            'id_doc_path' => 'permits/work/test.jpg',
            'status' => 'mep_review',
        ]);
    }

    private function whatsappSetting(): array
    {
        return [
            'base_url' => 'http://192.168.0.253:3000',
            'api_key' => str_repeat('a', 32),
            'session' => 'default',
            'timeout' => 10,
            'is_active' => true,
            'notify_applicants' => true,
        ];
    }

    private function campaignRecipient(AnnouncementCampaign $campaign, string $channel, string $destination): AnnouncementRecipient
    {
        return $campaign->recipients()->create([
            'channel' => $channel,
            'audience' => 'accounts',
            'recipient_name' => 'Penerima Uji',
            'destination' => $destination,
            'destination_hash' => hash('sha256', $destination),
            'status' => 'queued',
        ]);
    }
}
