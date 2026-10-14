<?php

namespace Tests\Feature;

use App\Models\EmailMessageLog;
use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\User;
use App\Models\WhatsappMessageLog;
use App\Models\WorkPermit;
use App\Services\PermitCommunicationTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitCommunicationTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_timeline_combines_channels_without_exposing_recipient_data(): void
    {
        $staff = $this->staff('MEP');
        $permit = $this->workPermit();
        $permit->statusLogs()->create([
            'actor_id' => $staff->id,
            'from_status' => 'mep_review',
            'to_status' => 'awaiting_payment',
            'action' => 'deposit_requested',
            'notes' => 'Deposit ditetapkan oleh MEP.',
        ]);
        EmailMessageLog::create([
            'recipient' => 'private@example.test',
            'recipient_hash' => hash('sha256', 'private@example.test'),
            'notification_type' => 'test',
            'event_key' => 'deposit_requested',
            'emailable_type' => $permit->getMorphClass(),
            'emailable_id' => $permit->id,
            'subject' => 'Pemberitahuan security deposit',
            'status' => 'sent',
            'attempts' => 1,
            'sent_at' => now(),
        ]);
        WhatsappMessageLog::create([
            'unique_key' => 'timeline-test-'.$permit->id,
            'permit_type' => 'work',
            'permit_id' => $permit->id,
            'event' => 'deposit_requested',
            'recipient_kind' => 'applicant',
            'recipient_name' => 'Pemohon Rahasia',
            'recipient_phone' => '6281234567890',
            'recipient_phone_hash' => hash('sha256', '6281234567890'),
            'message' => 'Isi pesan rahasia',
            'status' => 'sent',
            'attempts' => 1,
            'sent_at' => now(),
        ]);
        PermitNotification::create([
            'user_id' => $staff->id,
            'work_permit_id' => $permit->id,
            'type' => 'work_permit_update',
            'title' => 'Pembaruan Surat Izin Kerja',
            'body' => 'Status berubah.',
        ]);

        $items = app(PermitCommunicationTimeline::class)->forWork($permit);
        $titles = $items->pluck('title');
        $serialized = json_encode($items->all(), JSON_THROW_ON_ERROR);

        $this->assertSame(1, $titles->filter(fn (string $title): bool => $title === 'Permohonan diajukan')->count());
        $this->assertTrue($titles->contains('Security deposit diminta'));
        $this->assertTrue($titles->contains('Pemberitahuan security deposit'));
        $this->assertTrue($titles->contains('Pembaruan Surat Izin Kerja'));
        $this->assertStringNotContainsString('private@example.test', $serialized);
        $this->assertStringNotContainsString('6281234567890', $serialized);
        $this->assertStringNotContainsString('Isi pesan rahasia', $serialized);
    }

    public function test_admin_work_detail_renders_the_communication_timeline(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'division' => null,
            'phone' => '081111111111',
        ]);
        $permit = $this->workPermit();

        $this->actingAs($admin)
            ->get(route('staff.work-permits.show', $permit->public_token))
            ->assertOk()
            ->assertSee('Aktivitas dan komunikasi')
            ->assertSee('Permohonan diajukan');
    }

    public function test_secretary_loading_detail_renders_a_read_only_timeline(): void
    {
        $secretary = User::factory()->create([
            'role' => 'secretary',
            'division' => null,
            'phone' => '082222222222',
        ]);
        $permit = $this->loadingPermit();

        $this->actingAs($secretary)
            ->get(route('secretary.loading.show', $permit->permit_number))
            ->assertOk()
            ->assertSee('Aktivitas dan komunikasi')
            ->assertSee('Permohonan diajukan');
    }

    private function staff(string $division): User
    {
        return User::factory()->create([
            'role' => 'validator',
            'division' => $division,
            'phone' => fake()->unique()->numerify('08##########'),
        ]);
    }

    private function loadingPermit(): LoadingPermit
    {
        return LoadingPermit::create([
            'permit_number' => 'MBG/SIK/TEST/'.fake()->unique()->numerify('####'),
            'tenant_name' => 'Tenant Pengujian',
            'applicant_name' => 'Pemohon Pengujian',
            'applicant_phone' => '081200000001',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'item_count' => 1,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian',
            'id_doc_path' => 'permits/id-docs/test.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'pending',
        ]);
    }

    private function workPermit(): WorkPermit
    {
        return WorkPermit::create([
            'contractor_name' => 'Kontraktor Pengujian',
            'applicant_name' => 'Penanggung Jawab',
            'applicant_phone' => '081234567890',
            'work_location' => 'Unit GF-12',
            'work_category' => 'other',
            'assigned_division' => 'MEP',
            'work_type' => 'Perbaikan instalasi listrik',
            'work_schedule' => 'inside_store',
            'work_schedules' => ['inside_store'],
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'needs_water' => false,
            'security_deposit' => false,
            'id_doc_type' => 'ktp',
            'id_doc_path' => 'permits/work/id-docs/test.jpg',
            'status' => 'mep_review',
        ]);
    }
}
