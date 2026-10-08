<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureWebsiteOperating;
use App\Models\LoadingPermit;
use App\Models\User;
use App\Models\WorkPermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertHeader('X-Content-Type-Options', 'nosniff');
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

        $this->actingAs($mep)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($finance)->get($url)->assertForbidden();
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
