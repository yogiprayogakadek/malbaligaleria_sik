<?php

namespace Tests\Feature;

use App\Models\EmailMessageLog;
use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\User;
use App\Models\WhatsappMessageLog;
use App\Notifications\LoadingPermitApplicantMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCommunicationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_monitor_all_channels_and_open_related_permit(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000101']);
        $permit = $this->loadingPermit();

        EmailMessageLog::query()->create([
            'notification_id' => (string) Str::uuid(),
            'recipient' => 'tenant@example.test',
            'recipient_hash' => hash('sha256', 'tenant@example.test'),
            'notification_type' => LoadingPermitApplicantMail::class,
            'event_key' => LoadingPermitApplicantMail::SUBMITTED,
            'emailable_type' => LoadingPermit::class,
            'emailable_id' => $permit->id,
            'subject' => 'Email permohonan diterima',
            'status' => 'sent',
            'attempts' => 1,
            'sent_at' => now(),
        ]);

        WhatsappMessageLog::query()->create([
            'unique_key' => hash('sha256', 'communication-center-test'),
            'permit_type' => 'loading',
            'permit_id' => $permit->id,
            'event' => 'submitted',
            'recipient_kind' => 'applicant',
            'recipient_name' => 'Pemohon Uji',
            'recipient_phone' => '628123456789',
            'recipient_phone_hash' => hash('sha256', '628123456789'),
            'message' => 'Pesan permohonan',
            'status' => 'failed',
            'attempts' => 3,
            'error' => 'Gagal menghubungi https://internal.test untuk tenant@example.test 081234567890.',
        ]);

        PermitNotification::query()->create([
            'user_id' => $admin->id,
            'permit_id' => $permit->id,
            'type' => 'pending_review',
            'title' => 'Permohonan baru',
            'body' => 'Permohonan menunggu pemeriksaan.',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.communication-logs.index'));

        $response->assertOk()
            ->assertSee('Email permohonan diterima')
            ->assertSee('Submitted')
            ->assertSee('Permohonan baru')
            ->assertSee('Diterima kanal')
            ->assertSee('Belum dibaca')
            ->assertSee(route('admin.loading.show', $permit->permit_number), false)
            ->assertSee('[alamat disamarkan]')
            ->assertDontSee('internal.test')
            ->assertDontSee('tenant@example.test')
            ->assertDontSee('081234567890');
    }

    public function test_admin_can_filter_communication_by_channel_status_type_and_reference(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000102']);
        $permit = $this->loadingPermit();

        EmailMessageLog::query()->create([
            'recipient' => 'filter@example.test',
            'recipient_hash' => hash('sha256', 'filter@example.test'),
            'notification_type' => LoadingPermitApplicantMail::class,
            'event_key' => LoadingPermitApplicantMail::SUBMITTED,
            'emailable_type' => LoadingPermit::class,
            'emailable_id' => $permit->id,
            'subject' => 'Email yang dicari',
            'status' => 'failed',
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        PermitNotification::query()->create([
            'user_id' => $admin->id,
            'permit_id' => $permit->id,
            'type' => 'pending_review',
            'title' => 'Notifikasi lain',
            'body' => 'Tidak boleh tampil pada filter email.',
        ]);

        $this->actingAs($admin)->get(route('admin.communication-logs.index', [
            'channel' => 'email',
            'status' => 'failed',
            'permit_type' => 'loading',
            'q' => $permit->permit_number,
        ]))->assertOk()
            ->assertSee('Email yang dicari')
            ->assertSee('Kirim ulang')
            ->assertViewHas('logs', fn ($logs): bool => $logs->count() === 1
                && $logs->first()['channel'] === 'email');
    }

    public function test_non_admin_cannot_open_communication_center(): void
    {
        $validator = User::factory()->create([
            'role' => 'validator',
            'division' => 'TR',
            'phone' => '081200000103',
        ]);

        $this->actingAs($validator)
            ->get(route('admin.communication-logs.index'))
            ->assertForbidden();
    }

    private function loadingPermit(): LoadingPermit
    {
        return LoadingPermit::query()->create([
            'permit_number' => 'MBG/LO/X/0042',
            'tenant_name' => 'Tenant Uji',
            'applicant_name' => 'Pemohon Uji',
            'applicant_phone' => '081211112222',
            'applicant_email' => 'pemohon@example.test',
            'direction' => 'in',
            'start_date' => now(),
            'end_date' => now()->addDay(),
            'movement_time' => '22:00',
            'item_count' => 1,
            'item_unit' => 'koli',
            'item_description' => 'Peralatan tenant',
            'id_doc_path' => 'permits/test.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'pending',
        ]);
    }
}
