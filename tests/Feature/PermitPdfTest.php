<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureWebsiteOperating;
use App\Models\LoadingPermit;
use App\Models\User;
use App\Models\WorkPermit;
use App\Services\PermitPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class PermitPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureWebsiteOperating::class);
    }

    public function test_owner_downloads_an_approved_loading_permit_as_a_pdf_attachment(): void
    {
        $owner = User::factory()->create([
            'role' => 'tenant',
            'phone' => '081200000099',
        ]);
        $permit = $this->loadingPermit(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->get(route('loading.letter', $permit->permit_number));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_guest_needs_a_signed_url_to_download_a_loading_permit(): void
    {
        $permit = $this->loadingPermit();

        $this->get(route('loading.letter', $permit->permit_number))->assertForbidden();

        $url = URL::temporarySignedRoute('loading.letter', now()->addMinute(), [
            'permitNumber' => $permit->permit_number,
        ]);

        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_loading_letter_preview_has_back_and_download_actions(): void
    {
        $owner = User::factory()->create([
            'role' => 'tenant',
            'phone' => '081200000098',
        ]);
        $permit = $this->loadingPermit(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->get(route('loading.letter.preview', $permit->permit_number))
            ->assertOk()
            ->assertSee('data-document-back', false)
            ->assertSee('Kembali')
            ->assertSee('Unduh')
            ->assertSee(route('loading.letter.inline', $permit->permit_number), false)
            ->assertSee(route('loading.letter', $permit->permit_number), false);

        $inlineUrl = URL::temporarySignedRoute('loading.letter.inline', now()->addMinute(), [
            'permitNumber' => $permit->permit_number,
        ]);
        $inline = $this->actingAs($owner)->get($inlineUrl);
        $inline->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertStringContainsString("frame-ancestors 'self'", (string) $inline->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('inline;', (string) $inline->headers->get('Content-Disposition'));
    }

    public function test_applicant_downloads_an_approved_work_permit_as_a_pdf_attachment(): void
    {
        $permit = WorkPermit::create([
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
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
        $permit->workers()->create([
            'position' => 1,
            'name' => 'Pekerja Pertama',
            'identity_number' => 'ID-001',
        ]);

        $response = $this->get(route('work-permits.letter', $permit->applicant_token));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->get(route('work-permits.letter.preview', $permit->applicant_token))
            ->assertOk()
            ->assertSee('data-document-back', false)
            ->assertSee(route('work-permits.status', $permit->applicant_token), false)
            ->assertSee(route('work-permits.letter.inline', $permit->applicant_token), false);

        $inline = $this->get(route('work-permits.letter.inline', $permit->applicant_token));
        $inline->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertStringContainsString("frame-ancestors 'self'", (string) $inline->headers->get('Content-Security-Policy'));
    }

    public function test_pending_permits_do_not_expose_a_letter(): void
    {
        $loading = $this->loadingPermit(['status' => 'pending']);
        $url = URL::temporarySignedRoute('loading.letter', now()->addMinute(), [
            'permitNumber' => $loading->permit_number,
        ]);

        $this->get($url)->assertNotFound();
    }

    public function test_only_authorized_non_finance_staff_can_download_a_work_letter(): void
    {
        $permit = WorkPermit::create([
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
            'end_date' => now()->addDay()->toDateString(),
            'needs_water' => false,
            'security_deposit' => false,
            'id_doc_type' => 'ktp',
            'id_doc_path' => 'permits/work/id-docs/test.jpg',
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
        $mep = User::factory()->create([
            'role' => 'validator',
            'division' => 'MEP',
            'phone' => '081200000011',
        ]);
        $finance = User::factory()->create([
            'role' => 'validator',
            'division' => 'FIN',
            'phone' => '081200000012',
        ]);

        $url = route('staff.work-permits.letter', $permit->public_token);
        $inlineUrl = route('staff.work-permits.letter.inline', $permit->public_token);

        $this->actingAs($mep)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($mep)->get($inlineUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->actingAs($finance)->get($url)->assertForbidden();
    }

    public function test_official_letters_use_logo_watermarks_without_a_footer(): void
    {
        foreach (['pdf.loading-permit', 'pdf.work-permit'] as $view) {
            $contents = file_get_contents(resource_path('views/'.str_replace('.', '/', $view).'.blade.php'));

            $this->assertStringContainsString('class="watermark"', $contents);
            $this->assertStringContainsString('class="qr-logo"', $contents);
            $this->assertStringNotContainsString('document-footer', $contents);
        }

        $loading = file_get_contents(resource_path('views/pdf/loading-permit.blade.php'));
        $work = file_get_contents(resource_path('views/pdf/work-permit.blade.php'));
        $verification = file_get_contents(resource_path('views/pdf/partials/verification.blade.php'));
        foreach ([$loading, $work, $verification] as $contents) {
            $this->assertStringContainsString('validatorQrDataUri', $contents);
            $this->assertStringContainsString('signature-name', $contents);
        }

        $styles = file_get_contents(resource_path('views/pdf/partials/styles.blade.php'));
        $this->assertStringContainsString("font-family: 'DejaVu Serif'", $styles);
        $this->assertStringContainsString('opacity: .09', $styles);
        $this->assertStringContainsString('.qr-logo', $styles);
        $this->assertStringContainsString('top: 10px; left: 26px', $styles);
        $this->assertStringContainsString('border-radius: 50%; background: #ffffff', $styles);
        $this->assertStringContainsString('.validator-qr-mark', $styles);
        $this->assertStringContainsString('.validator-qr-logo', $styles);
    }

    public function test_validator_qr_opens_a_public_privacy_scoped_approval_record(): void
    {
        Storage::fake('local');
        $validator = User::factory()->create([
            'name' => 'Validator QR MBG',
            'email' => 'validator-qr@example.test',
            'phone' => '081299999999',
            'role' => 'validator',
            'division' => 'TR',
        ]);
        $permit = $this->loadingPermit(['reviewed_by' => $validator->id]);

        app(PermitPdfService::class)->sealLoadingPermit($permit);
        $permit->refresh();
        $this->assertSame('Validator QR MBG', $permit->official_approved_by_name);
        $this->assertSame('Tenant Relationship', $permit->official_approved_by_division);
        $validator->update(['name' => 'Nama Validator Diubah']);

        $response = $this->get(route('permit-approvals.verify', $permit->official_document_id));

        $response->assertOk()
            ->assertSee('Persetujuan tercatat dan sah')
            ->assertSee('Validator QR MBG')
            ->assertDontSee('Nama Validator Diubah')
            ->assertSee('Tenant Relationship')
            ->assertSee($permit->permit_number)
            ->assertDontSee('validator-qr@example.test')
            ->assertDontSee('081299999999')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_validator_qr_reports_a_tampered_official_document(): void
    {
        Storage::fake('local');
        $permit = $this->loadingPermit();
        app(PermitPdfService::class)->sealLoadingPermit($permit);
        $permit->refresh();
        Storage::disk('local')->put($permit->official_document_path, 'tampered document');

        $this->get(route('permit-approvals.verify', $permit->official_document_id))
            ->assertStatus(409)
            ->assertSee('Integritas dokumen tidak valid');

        $this->get('/verify/approval/1')->assertNotFound();
    }

    public function test_first_download_seals_the_loading_letter_and_later_data_changes_do_not_change_it(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create([
            'role' => 'tenant',
            'phone' => '081200000097',
        ]);
        $permit = $this->loadingPermit(['user_id' => $owner->id]);

        $first = $this->actingAs($owner)->get(route('loading.letter', $permit->permit_number));
        $first->assertOk();
        $permit->refresh();

        $this->assertNotNull($permit->official_document_id);
        $this->assertNotNull($permit->official_document_issued_at);
        $this->assertSame(hash('sha256', $first->getContent()), $permit->official_document_sha256);
        Storage::disk('local')->assertExists($permit->official_document_path);

        $permit->forceFill(['tenant_name' => 'Nama yang diubah setelah terbit'])->save();
        $second = $this->actingAs($owner)->get(route('loading.letter', $permit->permit_number));

        $second->assertOk()
            ->assertHeader('X-Document-Id', $permit->official_document_id)
            ->assertHeader('X-Document-SHA256', $permit->official_document_sha256);
        $this->assertSame($first->getContent(), $second->getContent());
    }

    public function test_tampered_official_document_is_never_returned(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create([
            'role' => 'tenant',
            'phone' => '081200000096',
        ]);
        $permit = $this->loadingPermit(['user_id' => $owner->id]);

        $this->actingAs($owner)->get(route('loading.letter', $permit->permit_number))->assertOk();
        $permit->refresh();
        Storage::disk('local')->put($permit->official_document_path, 'tampered document');

        $this->withExceptionHandling()
            ->actingAs($owner)
            ->get(route('loading.letter', $permit->permit_number))
            ->assertStatus(500);
    }

    public function test_official_document_metadata_cannot_be_rewritten_after_issuance(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create([
            'role' => 'tenant',
            'phone' => '081200000095',
        ]);
        $permit = $this->loadingPermit(['user_id' => $owner->id]);
        $this->actingAs($owner)->get(route('loading.letter', $permit->permit_number))->assertOk();

        $this->expectException(\LogicException::class);
        $permit->refresh()->forceFill(['official_document_sha256' => str_repeat('0', 64)])->save();
    }

    private function loadingPermit(array $attributes = []): LoadingPermit
    {
        return LoadingPermit::create(array_merge([
            'permit_number' => 'MBG/SIK/PDF/'.Str::upper(Str::random(8)),
            'tenant_name' => 'Tenant Pengujian',
            'applicant_name' => 'Pemohon Pengujian',
            'applicant_phone' => '081200000001',
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
            'barcode_token' => hash('sha256', Str::random(64)),
            'barcode_expires_at' => now()->addDay(),
            'reviewed_at' => now(),
        ], $attributes));
    }
}
