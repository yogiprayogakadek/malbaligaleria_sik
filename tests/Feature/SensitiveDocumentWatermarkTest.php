<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LoadingPermit;
use App\Models\User;
use App\Models\WorkPermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SensitiveDocumentWatermarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_image_response_is_watermarked_and_access_is_audited(): void
    {
        Storage::fake('local');
        $viewer = $this->staff('validator', 'TR', 'Petugas Watermark');
        $permit = LoadingPermit::query()->create($this->loadingData());
        $upload = UploadedFile::fake()->image('identity.jpg', 900, 600);
        $original = file_get_contents($upload->getRealPath());
        Storage::disk('local')->put($permit->id_doc_path, $original);

        $url = URL::temporarySignedRoute('tr.id-doc', now()->addMinutes(5), [
            'documentToken' => $permit->document_token,
        ]);
        $response = $this->actingAs($viewer)->get($url);

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Document-Watermark', 'applied')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertNotSame($original, $response->getContent());
        $this->assertNotFalse(imagecreatefromstring($response->getContent()));
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $viewer->id,
            'event' => 'viewed',
            'auditable_type' => LoadingPermit::class,
            'auditable_id' => $permit->id,
        ]);
        $log = AuditLog::query()->where('event', 'viewed')->firstOrFail();
        $this->assertSame('identity', $log->new_values['document_kind']);
        $this->assertTrue($log->new_values['watermark_applied']);
    }

    public function test_staff_pdf_has_viewer_identity_embedded_on_the_response(): void
    {
        Storage::fake('local');
        $viewer = $this->staff('validator', 'MEP', 'PDF Viewer');
        $permit = WorkPermit::query()->create(array_merge($this->workData(), [
            'assignment_letter_path' => 'permits/work/assignment-letters/task.pdf',
            'assignment_letter_type' => 'pdf',
        ]));
        $pdf = new \FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, 'Isi surat tugas');
        Storage::disk('local')->put($permit->assignment_letter_path, $pdf->Output('S'));

        $url = URL::temporarySignedRoute('staff.work-permits.assignment-letter', now()->addMinutes(5), [
            'token' => $permit->public_token,
        ]);
        $response = $this->actingAs($viewer)->get($url);

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Document-Watermark', 'applied');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('PDF VIEWER', $response->getContent());
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $viewer->id,
            'event' => 'viewed',
            'auditable_type' => WorkPermit::class,
            'auditable_id' => $permit->id,
        ]);
    }

    public function test_staff_pdf_payment_proof_with_compressed_xref_falls_back_safely_and_allows_preview_and_download(): void
    {
        Storage::fake('local');
        $viewer = $this->staff('validator', 'FIN', 'Finance Verifier');
        $permit = WorkPermit::query()->create(array_merge($this->workData(), [
            'status' => 'payment_review',
            'deposit_required' => true,
            'security_deposit' => true,
            'deposit_amount' => 2500000,
            'payment_proof_path' => 'permits/work/payment-proofs/bank-receipt.pdf',
            'payment_proof_type' => 'pdf',
        ]));

        // Minimal PDF 1.5 with a compressed XRef stream (unsupported by free FPDI)
        $prefix = "%PDF-1.5\n1 0 obj\n<< /Type /Catalog >>\nendobj\n";
        $xrefOffset = strlen($prefix);
        $xrefData = gzcompress("\x00\x00\x00\x00\x01\x00\x00\x09");
        $compressedPdf = $prefix."2 0 obj\n<< /Type /XRef /Size 2 /W [1 2 1] /Filter /FlateDecode /Length ".strlen($xrefData)." >>\nstream\n{$xrefData}\nendstream\nendobj\nstartxref\n{$xrefOffset}\n%%EOF\n";
        Storage::disk('local')->put($permit->payment_proof_path, $compressedPdf);

        $previewUrl = URL::temporarySignedRoute('staff.work-permits.payment-proof.preview', now()->addMinutes(5), [
            'token' => $permit->public_token,
        ]);
        $this->actingAs($viewer)->get($previewUrl)
            ->assertOk()
            ->assertSee('document-preview-open-tab', false)
            ->assertSee('document-preview-download', false);

        $inlineUrl = URL::temporarySignedRoute('staff.work-permits.payment-proof', now()->addMinutes(5), [
            'token' => $permit->public_token,
        ]);
        $inlineResponse = $this->actingAs($viewer)->get($inlineUrl);
        $inlineResponse->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Document-Watermark', 'fallback-unsupported-pdf')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertStringContainsString("frame-ancestors 'self'", (string) $inlineResponse->headers->get('Content-Security-Policy'));
        $this->assertSame($compressedPdf, $inlineResponse->getContent());

        $log = AuditLog::query()->where('event', 'viewed')->latest('id')->firstOrFail();
        $this->assertSame('payment_proof', $log->new_values['document_kind']);
        $this->assertFalse($log->new_values['watermark_applied']);
        $this->assertSame('unsupported_pdf_compression', $log->new_values['watermark_fallback']);

        $downloadUrl = URL::temporarySignedRoute('staff.work-permits.payment-proof', now()->addMinutes(5), [
            'token' => $permit->public_token,
            'download' => 1,
        ]);
        $this->actingAs($viewer)->get($downloadUrl)
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="bukti-pembayaran-terlindungi.pdf"');
    }

    public function test_invalid_sensitive_document_is_not_returned_without_a_watermark(): void
    {
        Storage::fake('local');
        $viewer = $this->staff('validator', 'TR', 'Petugas Aman');
        $permit = LoadingPermit::query()->create($this->loadingData());
        Storage::disk('local')->put($permit->id_doc_path, 'bukan gambar');

        $url = URL::temporarySignedRoute('tr.id-doc', now()->addMinutes(5), [
            'documentToken' => $permit->document_token,
        ]);

        $this->actingAs($viewer)->get($url)->assertUnprocessable();
        $this->assertDatabaseMissing('audit_logs', [
            'actor_id' => $viewer->id,
            'event' => 'viewed',
        ]);
    }

    private function staff(string $role, ?string $division, string $name): User
    {
        return User::factory()->create([
            'name' => $name,
            'phone' => fake()->unique()->numerify('08##########'),
            'role' => $role,
            'division' => $division,
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function loadingData(): array
    {
        return [
            'permit_number' => 'MBG/SIK/WATERMARK/0001',
            'tenant_name' => 'Tenant Watermark',
            'applicant_name' => 'Pemohon Watermark',
            'applicant_phone' => '081234567890',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'item_count' => 1,
            'item_unit' => 'koli',
            'item_description' => 'Barang',
            'id_doc_path' => 'permits/id-docs/watermark.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'pending',
        ];
    }

    /** @return array<string, mixed> */
    private function workData(): array
    {
        return [
            'contractor_name' => 'Kontraktor Watermark',
            'applicant_name' => 'Pemohon Watermark',
            'applicant_phone' => '081234567890',
            'work_location' => 'Unit GF-12',
            'work_category' => 'other',
            'assigned_division' => 'MEP',
            'work_type' => 'Perbaikan',
            'work_schedule' => 'inside_store',
            'work_schedules' => ['inside_store'],
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'needs_water' => false,
            'security_deposit' => false,
            'id_doc_type' => 'ktp',
            'id_doc_path' => 'permits/work/id-docs/watermark.jpg',
            'status' => 'mep_review',
        ];
    }
}
