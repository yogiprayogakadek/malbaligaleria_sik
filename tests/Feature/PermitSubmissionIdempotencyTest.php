<?php

namespace Tests\Feature;

use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\User;
use App\Models\WorkPermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PermitSubmissionIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_loading_submission_creates_one_permit_and_one_notification(): void
    {
        Notification::fake();
        Storage::fake('local');
        User::factory()->create(['role' => 'admin', 'phone' => '081200000001', 'is_active' => true]);

        $token = $this->get(route('loading.create'))->viewData('submissionToken');
        $first = $this->loadingData($token, 'ktp-first.jpg');
        $second = $this->loadingData($token, 'ktp-second.jpg');

        $this->post(route('loading.store'), $first)->assertRedirect();
        $this->post(route('loading.store'), $second)
            ->assertRedirect()
            ->assertSessionHas('success', 'Permohonan ini sudah diterima sebelumnya. Tidak ada data ganda yang dibuat.');

        $this->assertDatabaseCount('loading_permits', 1);
        $this->assertDatabaseCount('permit_notifications', 1);
        $this->assertNotNull(LoadingPermit::firstOrFail()->submission_token_hash);
        Storage::disk('local')->assertMissing('permits/id-docs/'.$second['id_doc']->hashName());
    }

    public function test_repeated_work_submission_creates_one_permit_worker_and_notification(): void
    {
        Notification::fake();
        Storage::fake('local');
        User::factory()->create(['role' => 'admin', 'phone' => '081200000002', 'is_active' => true]);

        $token = $this->get(route('work-permits.create'))->viewData('submissionToken');

        $this->post(route('work-permits.store'), $this->workData($token, 'ktp-first.jpg'))->assertRedirect();
        $this->post(route('work-permits.store'), $this->workData($token, 'ktp-second.jpg'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Permohonan ini sudah diterima sebelumnya. Tidak ada data ganda yang dibuat.');

        $this->assertDatabaseCount('work_permits', 1);
        $this->assertDatabaseCount('work_permit_workers', 1);
        $this->assertSame(1, PermitNotification::whereNotNull('work_permit_id')->count());
        $this->assertNotNull(WorkPermit::firstOrFail()->submission_token_hash);
    }

    public function test_submission_token_must_belong_to_the_current_session(): void
    {
        Storage::fake('local');
        $data = $this->loadingData('00000000-0000-4000-8000-000000000000', 'ktp.jpg');

        $this->from(route('loading.create'))
            ->post(route('loading.store'), $data)
            ->assertRedirect(route('loading.create'))
            ->assertSessionHasErrors('submission_token');

        $this->assertDatabaseCount('loading_permits', 0);
    }

    /** @return array<string, mixed> */
    private function loadingData(string $token, string $filename): array
    {
        return [
            'submission_token' => $token,
            'tenant_name' => 'Tenant Idempotensi',
            'applicant_name' => 'Pemohon Pengujian',
            'applicant_phone' => '081234567890',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'movement_time' => '22:30',
            'item_count' => 2,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian',
            'id_doc_type' => 'ktp',
            'id_doc' => UploadedFile::fake()->image($filename, 600, 400),
        ];
    }

    /** @return array<string, mixed> */
    private function workData(string $token, string $filename): array
    {
        return [
            'submission_token' => $token,
            'contractor_name' => 'Kontraktor Idempotensi',
            'applicant_name' => 'Pemohon Pengujian',
            'applicant_phone' => '081234567890',
            'workers' => [['name' => 'Pekerja Satu', 'identity_number' => null]],
            'work_location' => 'Unit GF-12',
            'work_category' => 'other',
            'work_type' => 'Perbaikan instalasi listrik',
            'work_schedules' => ['inside_store'],
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'needs_water' => '0',
            'id_doc_type' => 'ktp',
            'id_doc' => UploadedFile::fake()->image($filename, 600, 400),
            'consent' => '1',
        ];
    }
}
