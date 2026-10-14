<?php

namespace Tests\Feature;

use App\Models\LoadingPermit;
use App\Models\User;
use App\Models\WorkPermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_the_filtered_report_dashboard(): void
    {
        $admin = $this->admin();
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081222222222']);
        $loading = $this->loadingPermit(['status' => 'approved']);
        $work = $this->workPermit(['status' => 'mep_review']);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'date_from' => now()->subDay()->toDateString(),
                'date_to' => now()->toDateString(),
                'permit_type' => 'all',
                'status_group' => 'all',
            ]))
            ->assertOk()
            ->assertSee('Aktivitas permohonan')
            ->assertSee($loading->permit_number)
            ->assertSee($work->permit_number)
            ->assertSee('Excel')
            ->assertSee('PDF');

        $this->actingAs($tenant)->get(route('admin.reports.index'))->assertForbidden();
    }

    public function test_report_filters_type_status_search_and_rejects_an_excessive_range(): void
    {
        $admin = $this->admin();
        $visible = $this->loadingPermit([
            'tenant_name' => 'Tenant Dicari',
            'status' => 'rejected',
        ]);
        $hidden = $this->workPermit(['contractor_name' => 'Kontraktor Lain', 'status' => 'rejected']);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'date_from' => now()->subDays(10)->toDateString(),
                'date_to' => now()->toDateString(),
                'permit_type' => 'loading',
                'status_group' => 'rejected',
                'search' => 'Tenant Dicari',
            ]))
            ->assertOk()
            ->assertSee($visible->permit_number)
            ->assertDontSee($hidden->permit_number);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'date_from' => now()->subDays(400)->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertSessionHasErrors('date_to');
    }

    public function test_excel_export_is_a_real_workbook_and_keeps_untrusted_text_as_text(): void
    {
        $admin = $this->admin();
        $this->loadingPermit(['tenant_name' => '=HYPERLINK("https://invalid.test")']);

        $response = $this->actingAs($admin)->get(route('admin.reports.excel'));
        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload();

        $content = $response->streamedContent();
        $this->assertStringStartsWith('PK', $content);
        $path = tempnam(sys_get_temp_dir(), 'sik-report-');
        file_put_contents($path, $content);

        try {
            $workbook = IOFactory::load($path);
            $cell = $workbook->getSheetByName('Data')->getCell('D2');
            $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
            $this->assertSame('=HYPERLINK("https://invalid.test")', $cell->getValue());
            $this->assertSame('Ringkasan', $workbook->getSheet(0)->getTitle());
        } finally {
            @unlink($path);
        }
    }

    public function test_pdf_export_downloads_a_pdf_without_contact_or_document_data(): void
    {
        $admin = $this->admin();
        $permit = $this->loadingPermit([
            'applicant_phone' => '081299999999',
            'id_doc_path' => 'permits/id-docs/private-document.jpg',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.pdf'));
        $response->assertOk()->assertDownload();
        $content = $response->getContent();

        $this->assertStringStartsWith('%PDF', $content);
        $this->assertStringNotContainsString($permit->applicant_phone, $content);
        $this->assertStringNotContainsString($permit->id_doc_path, $content);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'division' => null,
            'phone' => '081111111111',
        ]);
    }

    private function loadingPermit(array $overrides = []): LoadingPermit
    {
        return LoadingPermit::create(array_merge([
            'permit_number' => 'MBG/SIK/REPORT/'.fake()->unique()->numerify('####'),
            'tenant_name' => 'Tenant Laporan',
            'applicant_name' => 'Pemohon Loading',
            'applicant_phone' => '081200000001',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'item_count' => 2,
            'item_unit' => 'koli',
            'item_description' => 'Barang laporan',
            'id_doc_path' => 'permits/id-docs/test.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'pending',
        ], $overrides));
    }

    private function workPermit(array $overrides = []): WorkPermit
    {
        return WorkPermit::create(array_merge([
            'contractor_name' => 'Kontraktor Laporan',
            'applicant_name' => 'Pemohon SIK',
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
        ], $overrides));
    }
}
