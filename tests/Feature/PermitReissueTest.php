<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireSensitiveActionConfirmation;
use App\Models\LoadingPermit;
use App\Models\User;
use App\Models\WorkPermit;
use App\Services\PermitPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PermitReissueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(RequireSensitiveActionConfirmation::class);
        Storage::fake('local');
        Notification::fake();
    }

    public function test_admin_reissues_loading_with_a_new_document_and_scanner_qr(): void
    {
        $admin = $this->admin();
        $permit = $this->loadingPermit(['reviewed_by' => $admin->id]);
        app(PermitPdfService::class)->sealLoadingPermit($permit);
        $permit->refresh();
        $oldDocumentId = $permit->official_document_id;
        $oldHash = $permit->official_document_sha256;
        $oldPath = $permit->official_document_path;
        $oldScanToken = $permit->barcode_token;

        $this->actingAs($admin)->post(route('admin.permit-reissues.loading', $permit), [
            'reason' => 'Nama area pada surat lama perlu diperbaiki.',
            'current_password' => 'password',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $permit->refresh();
        $this->assertNotSame($oldDocumentId, $permit->official_document_id);
        $this->assertNotSame($oldHash, $permit->official_document_sha256);
        $this->assertNotSame($oldScanToken, $permit->barcode_token);
        Storage::disk('local')->assertExists($oldPath);
        Storage::disk('local')->assertExists($permit->official_document_path);
        $this->assertDatabaseHas('official_document_versions', [
            'permit_type' => 'loading',
            'permit_id' => $permit->id,
            'version' => 1,
            'document_id' => $oldDocumentId,
            'replacement_document_id' => $permit->official_document_id,
            'replaced_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'official_document_reissued',
            'auditable_type' => LoadingPermit::class,
            'auditable_id' => $permit->id,
        ]);

        $this->get(route('permit-approvals.verify', $oldDocumentId))
            ->assertStatus(409)
            ->assertSee('Surat ini telah digantikan');
        $this->get(route('permit-approvals.verify', $permit->official_document_id))->assertOk();
        $this->getJson(route('scanner.verify', ['token' => $oldScanToken]))->assertNotFound();
        $this->getJson(route('scanner.verify', ['token' => $permit->barcode_token]))
            ->assertOk()
            ->assertJsonPath('document_id', $permit->official_document_id);
        $this->actingAs($admin)->get(route('admin.loading.show', $permit->permit_number))
            ->assertOk()
            ->assertSee('Riwayat surat resmi')
            ->assertSee('Nama area pada surat lama perlu diperbaiki.');
    }

    public function test_work_reissue_preserves_staff_link_but_rotates_scanner_token(): void
    {
        $admin = $this->admin();
        $permit = $this->workPermit(['reviewed_by' => $admin->id]);
        app(PermitPdfService::class)->sealWorkPermit($permit);
        $permit->refresh();
        $staffToken = $permit->public_token;

        $this->actingAs($admin)->post(route('admin.permit-reissues.work', $permit), [
            'reason' => 'Rincian lokasi pada surat perlu dikoreksi.',
            'current_password' => 'password',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $permit->refresh();
        $this->assertSame($staffToken, $permit->public_token);
        $this->assertNotNull($permit->official_scan_token);
        $this->actingAs($admin)->get(route('staff.work-permits.show', $staffToken))->assertOk();
        $this->getJson(route('scanner.verify', ['token' => $staffToken]))->assertNotFound();
        $this->getJson(route('scanner.verify', ['token' => $permit->official_scan_token]))
            ->assertOk()
            ->assertJsonPath('document_id', $permit->official_document_id);
    }

    public function test_non_admin_cannot_reissue_and_tampered_source_is_rejected(): void
    {
        $admin = $this->admin();
        $validator = User::factory()->create([
            'role' => 'validator',
            'division' => 'TR',
            'phone' => '081200009992',
        ]);
        $permit = $this->loadingPermit(['reviewed_by' => $validator->id]);
        app(PermitPdfService::class)->sealLoadingPermit($permit);
        $permit->refresh();

        $this->actingAs($validator)->post(route('admin.permit-reissues.loading', $permit), [
            'reason' => 'Alasan penerbitan ulang yang cukup panjang.',
            'current_password' => 'password',
        ])->assertForbidden();

        Storage::disk('local')->put($permit->official_document_path, 'tampered');
        $this->withExceptionHandling()->actingAs($admin)->post(route('admin.permit-reissues.loading', $permit), [
            'reason' => 'Alasan penerbitan ulang yang cukup panjang.',
            'current_password' => 'password',
        ])->assertStatus(500);

        $this->assertDatabaseCount('official_document_versions', 0);
    }

    public function test_event_reissue_is_recorded_as_an_event_document_version(): void
    {
        $admin = $this->admin();
        $permit = $this->workPermit([
            'permit_kind' => 'event',
            'assigned_division' => 'EP',
            'event_type' => 'tenant',
            'work_category' => 'mall_event',
            'event_requirements' => [],
            'reviewed_by' => $admin->id,
        ]);
        app(PermitPdfService::class)->sealWorkPermit($permit);

        $this->actingAs($admin)->post(route('admin.permit-reissues.work', $permit), [
            'reason' => 'Nama kegiatan pada surat lama perlu diperbaiki.',
            'current_password' => 'password',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('official_document_versions', [
            'permit_type' => 'event',
            'permit_id' => $permit->id,
            'version' => 1,
        ]);
        $this->actingAs($admin)->get(route('staff.work-permits.show', $permit->public_token))
            ->assertOk()
            ->assertSee('Nama kegiatan pada surat lama perlu diperbaiki.');
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'phone' => '081200009991',
        ]);
    }

    private function loadingPermit(array $attributes = []): LoadingPermit
    {
        return LoadingPermit::query()->create(array_merge([
            'permit_number' => 'MBG/SIK/REISSUE/'.Str::upper(Str::random(8)),
            'tenant_name' => 'Tenant Pengujian',
            'applicant_name' => 'Pemohon Pengujian',
            'applicant_phone' => '081200000001',
            'applicant_email' => 'pemohon@example.test',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'movement_time' => '22:30',
            'item_count' => 2,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian',
            'id_doc_path' => 'permits/id-docs/test.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'approved',
            'barcode_token' => Str::random(64),
            'barcode_expires_at' => now()->addDay(),
            'reviewed_at' => now(),
        ], $attributes));
    }

    private function workPermit(array $attributes = []): WorkPermit
    {
        return WorkPermit::query()->create(array_merge([
            'permit_kind' => 'work',
            'contractor_name' => 'Kontraktor Pengujian',
            'tenant_name' => 'Tenant Pengujian',
            'applicant_name' => 'Penanggung Jawab',
            'applicant_phone' => '081234567890',
            'applicant_email' => 'pekerja@example.test',
            'work_location' => 'Unit GF-12',
            'work_category' => 'other',
            'assigned_division' => 'MEP',
            'work_type' => 'Perbaikan instalasi listrik',
            'work_schedule' => 'inside_store',
            'work_schedules' => ['inside_store'],
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'needs_water' => false,
            'needs_lighting' => false,
            'security_deposit' => false,
            'id_doc_type' => 'ktp',
            'id_doc_path' => 'permits/work/id-docs/test.jpg',
            'status' => 'approved',
            'reviewed_at' => now(),
        ], $attributes));
    }
}
