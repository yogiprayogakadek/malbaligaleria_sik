<?php

namespace Tests\Feature;

use App\Models\LoadingPermit;
use App\Models\OperatingSchedule;
use App\Models\User;
use App\Models\WorkPermit;
use App\Support\ApplicantStatusUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApplicantStatusAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_status_links_and_tracking_remain_available_outside_submission_hours(): void
    {
        OperatingSchedule::query()->update(['is_open' => false, 'is_24_hours' => false]);
        $owner = User::factory()->create(['role' => 'tenant', 'phone' => '081234567890']);
        $loading = LoadingPermit::create($this->loadingData($owner));
        $work = WorkPermit::create($this->workData($owner));

        $this->get(ApplicantStatusUrl::loading($loading))
            ->assertOk()
            ->assertSee($loading->permit_number);
        $this->get(ApplicantStatusUrl::work($work))
            ->assertOk()
            ->assertSee($work->permit_number);
        $this->post(route('portal.track'), [
            'permit_number' => $loading->permit_number,
            'phone' => $loading->applicant_phone,
        ])->assertOk()->assertSee($loading->permit_number);

        $this->get(route('loading.create'))->assertServiceUnavailable();
        $this->get(route('work-permits.create'))->assertServiceUnavailable();
    }

    public function test_database_failure_uses_the_custom_service_unavailable_page(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/database-unavailable', fn () => DB::table('__missing_health_table')->first());

        $this->withExceptionHandling()
            ->get('/_test/database-unavailable')
            ->assertServiceUnavailable()
            ->assertSee('Layanan sementara tidak tersedia')
            ->assertDontSee('SQLSTATE');
    }

    private function loadingData(User $owner): array
    {
        return [
            'permit_number' => 'MBG/SIK/STATUS/0001',
            'user_id' => $owner->id,
            'tenant_name' => 'Tenant Status',
            'applicant_name' => 'Pemohon Status',
            'applicant_phone' => '081234567890',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'movement_time' => '22:30',
            'item_count' => 2,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian',
            'id_doc_path' => 'permits/id-docs/status.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'pending',
        ];
    }

    private function workData(User $owner): array
    {
        return [
            'user_id' => $owner->id,
            'contractor_name' => 'Kontraktor Status',
            'applicant_name' => 'Pemohon Status',
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
            'id_doc_path' => 'permits/work/id-docs/status.jpg',
            'status' => 'mep_review',
        ];
    }
}
