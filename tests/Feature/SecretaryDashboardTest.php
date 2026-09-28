<?php

namespace Tests\Feature;

use App\Events\LoadingPermitSubmitted;
use App\Events\WorkPermitSubmitted;
use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\User;
use App\Models\WorkPermit;
use App\Notifications\LoadingPermitSubmittedPush;
use App\Notifications\WorkPermitSubmittedPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SecretaryDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretary_is_redirected_to_a_read_only_dashboard(): void
    {
        $secretary = $this->secretary();
        $permit = WorkPermit::create($this->workPermitData());

        $this->actingAs($secretary)
            ->get(route('secretary.index'))
            ->assertOk()
            ->assertSee('Dashboard Secretary')
            ->assertSee('Akses ini hanya untuk melihat data')
            ->assertSee($permit->permit_number);

        $this->actingAs($secretary)
            ->post(route('mep.work-permits.deposit', $permit->public_token), ['deposit_required' => '0'])
            ->assertForbidden();

        $this->actingAs($secretary)
            ->post(route('tr.work-permits.decision', $permit->public_token), ['decision' => 'approved'])
            ->assertForbidden();
    }

    public function test_login_ignores_a_stale_destination_from_another_staff_role(): void
    {
        $secretary = $this->secretary();

        $this->withSession(['url.intended' => route('tr.index')])
            ->post(route('login.post'), [
                'login' => $secretary->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('secretary.index'));
    }

    public function test_empty_dashboard_keeps_the_datatable_structure_without_summary_cards(): void
    {
        $secretary = $this->secretary();

        $this->actingAs($secretary)
            ->get(route('secretary.index', ['status' => 'today']))
            ->assertOk()
            ->assertSee('Daftar Permohonan')
            ->assertSee('TENANT / PEMOHON')
            ->assertSee('Belum ada permohonan')
            ->assertSee('dt-empty-state', false)
            ->assertDontSee('validator-metrics', false);
    }

    public function test_secretary_can_view_work_permit_but_not_private_documents(): void
    {
        Storage::fake('local');
        $secretary = $this->secretary();
        $permit = WorkPermit::create(array_merge($this->workPermitData(), [
            'payment_proof_path' => 'permits/work/payment-proofs/test.pdf',
            'refund_proof_path' => 'permits/work/refund-proofs/test.pdf',
            'deposit_required' => true,
        ]));
        Storage::disk('local')->put($permit->id_doc_path, 'identity');
        Storage::disk('local')->put($permit->payment_proof_path, 'payment');
        Storage::disk('local')->put($permit->refund_proof_path, 'refund');

        $this->actingAs($secretary)
            ->get(route('staff.work-permits.show', $permit->public_token))
            ->assertOk()
            ->assertSee($permit->permit_number)
            ->assertDontSee('Lihat Dokumen Identitas')
            ->assertDontSee('Lihat Bukti Pembayaran')
            ->assertDontSee('Lihat Bukti Pengembalian');

        foreach (['document', 'payment-proof', 'refund-proof'] as $route) {
            $url = URL::temporarySignedRoute("staff.work-permits.{$route}", now()->addMinutes(5), [
                'token' => $permit->public_token,
            ]);
            $this->actingAs($secretary)->get($url)->assertForbidden();
        }
    }

    public function test_loading_and_work_submissions_notify_secretary(): void
    {
        Event::fake([LoadingPermitSubmitted::class, WorkPermitSubmitted::class]);
        Notification::fake();
        Storage::fake('local');
        $secretary = $this->secretary();

        $this->post(route('loading.store'), $this->loadingFormData())->assertRedirect();
        $this->post(route('work-permits.store'), $this->workFormData())->assertRedirect();

        $this->assertDatabaseCount('permit_notifications', 2);
        $this->assertDatabaseHas('permit_notifications', ['user_id' => $secretary->id, 'title' => 'Permohonan Baru']);
        $this->assertDatabaseHas('permit_notifications', ['user_id' => $secretary->id, 'title' => 'Permohonan Izin Kerja Baru']);
        Notification::assertSentTo($secretary, LoadingPermitSubmittedPush::class);
        Notification::assertSentTo($secretary, WorkPermitSubmittedPush::class);

        Event::assertDispatched(LoadingPermitSubmitted::class, fn (LoadingPermitSubmitted $event): bool =>
            $event->notification->user_id === $secretary->id
            && str_contains($event->broadcastWith()['notification']['read_url'], '/secretary/notifications/'));
        Event::assertDispatched(WorkPermitSubmitted::class, fn (WorkPermitSubmitted $event): bool =>
            $event->notification->user_id === $secretary->id
            && str_contains($event->broadcastWith()['notification']['read_url'], '/secretary/notifications/'));

        $this->actingAs($secretary)
            ->getJson(route('secretary.notifications.feed', ['after_id' => 0, 'category' => 'all']))
            ->assertOk()
            ->assertJsonPath('pending_count', 2)
            ->assertJsonCount(2, 'notifications');
    }

    public function test_inactive_secretary_is_logged_out(): void
    {
        $secretary = $this->secretary(false);

        $this->actingAs($secretary)
            ->get(route('secretary.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_read_notifications_disappear_and_clear_only_removes_own_notifications(): void
    {
        $secretary = $this->secretary();
        $otherSecretary = $this->secretary();
        $readNotification = PermitNotification::create([
            'user_id' => $secretary->id,
            'type' => 'pending_review',
            'title' => 'Notifikasi untuk Dibaca',
            'body' => 'Isi notifikasi pertama.',
        ]);
        PermitNotification::create([
            'user_id' => $secretary->id,
            'type' => 'pending_review',
            'title' => 'Notifikasi untuk Dihapus',
            'body' => 'Isi notifikasi kedua.',
        ]);
        $otherNotification = PermitNotification::create([
            'user_id' => $otherSecretary->id,
            'type' => 'pending_review',
            'title' => 'Notifikasi Akun Lain',
            'body' => 'Tidak boleh ikut terhapus.',
        ]);

        $this->actingAs($secretary)->get(route('secretary.index'))
            ->assertSee('Notifikasi untuk Dibaca');

        $this->actingAs($secretary)
            ->post(route('secretary.notifications.read', $readNotification))
            ->assertRedirect(route('secretary.index'));

        $this->actingAs($secretary)->get(route('secretary.index'))
            ->assertDontSee('Notifikasi untuk Dibaca')
            ->assertSee('Notifikasi untuk Dihapus');

        $this->actingAs($secretary)
            ->post(route('secretary.notifications.clear'))
            ->assertRedirect();

        $this->assertDatabaseMissing('permit_notifications', ['user_id' => $secretary->id]);
        $this->assertDatabaseHas('permit_notifications', ['id' => $otherNotification->id, 'user_id' => $otherSecretary->id]);
    }

    private function secretary(bool $active = true): User
    {
        return User::factory()->create([
            'phone' => fake()->unique()->numerify('08##########'),
            'role' => 'secretary',
            'division' => null,
            'is_active' => $active,
        ]);
    }

    /** @return array<string, mixed> */
    private function loadingFormData(): array
    {
        return [
            'tenant_name' => 'Tenant Secretary Test',
            'applicant_name' => 'Pemohon Loading',
            'applicant_phone' => '081200000101',
            'applicant_email' => null,
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'movement_time' => '22:30',
            'item_count' => 3,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian secretary',
            'id_doc' => UploadedFile::fake()->image('ktp.jpg', 600, 400),
            'id_doc_type' => 'ktp',
        ];
    }

    /** @return array<string, mixed> */
    private function workFormData(): array
    {
        return [
            'contractor_name' => 'Kontraktor Secretary Test',
            'applicant_name' => 'Pemohon Kerja',
            'applicant_phone' => '081200000102',
            'applicant_email' => null,
            'workers' => [['name' => 'Pekerja Satu', 'identity_number' => null]],
            'work_location' => 'Unit GF-12',
            'work_category' => 'other',
            'work_type' => 'Perbaikan instalasi listrik',
            'work_schedules' => ['inside_store'],
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'needs_water' => '0',
            'id_doc_type' => 'ktp',
            'id_doc' => UploadedFile::fake()->image('ktp-work.jpg', 600, 400),
            'consent' => '1',
        ];
    }

    /** @return array<string, mixed> */
    private function workPermitData(): array
    {
        return [
            'contractor_name' => 'Kontraktor Secretary Test',
            'applicant_name' => 'Pemohon Kerja',
            'applicant_phone' => '081200000103',
            'work_location' => 'Unit GF-12',
            'work_category' => 'other',
            'assigned_division' => 'MEP',
            'work_type' => 'Perbaikan instalasi listrik',
            'work_schedule' => 'inside_store',
            'work_schedules' => ['inside_store'],
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'needs_water' => false,
            'security_deposit' => false,
            'id_doc_type' => 'ktp',
            'id_doc_path' => 'permits/work/id-docs/test.jpg',
            'status' => 'mep_review',
        ];
    }
}
