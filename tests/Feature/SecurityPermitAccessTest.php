<?php

namespace Tests\Feature;

use App\Models\LoadingPermit;
use App\Models\SecurityGateEvent;
use App\Models\User;
use App\Models\WorkPermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityPermitAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_security_archive_combines_permit_types_and_filters_today_search_and_revocations(): void
    {
        $security = $this->security();
        $loading = $this->loadingPermit([
            'tenant_name' => 'Tenant Arsip Lama',
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);
        $work = $this->workPermit([
            'contractor_name' => 'Kontraktor Hari Ini',
            'work_type' => 'Perbaikan panel listrik utama',
            'other_work_type' => 'atm_repair',
            'status' => 'completed',
        ]);
        $event = $this->workPermit([
            'permit_kind' => 'event',
            'contractor_name' => 'Penyelenggara Festival',
            'official_scan_token' => Str::random(64),
        ]);
        $completedLoading = $this->loadingPermit([
            'direction' => 'out',
            'item_count' => 1,
        ]);
        SecurityGateEvent::query()->create([
            'permit_type' => 'loading',
            'permit_id' => $completedLoading->id,
            'event' => 'check_out',
            'item_quantity' => 1,
            'token_hash' => hash('sha256', $completedLoading->barcode_token),
            'occurred_at' => now(),
        ]);
        $revoked = $this->loadingPermit([
            'tenant_name' => 'Tenant Dicabut',
            'revoked_at' => now(),
        ]);

        $this->actingAs($security)->get(route('security.index'))
            ->assertOk()
            ->assertSee($loading->permit_number)
            ->assertSee($work->permit_number)
            ->assertSee('Perbaikan panel listrik utama')
            ->assertSee('Perbaikan ATM')
            ->assertSee($event->permit_number)
            ->assertSee('Loading / Unloading')
            ->assertSee('Surat Izin Kerja')
            ->assertSee('Surat Izin Event')
            ->assertSee(route('scanner.index', ['token' => $event->official_scan_token]), false)
            ->assertSee('Selesai')
            ->assertDontSee(route('scanner.index', ['token' => $completedLoading->barcode_token]), false)
            ->assertDontSee($revoked->permit_number);

        $this->actingAs($security)->get(route('security.index', ['status' => 'today']))
            ->assertOk()
            ->assertDontSee($loading->permit_number)
            ->assertSee($work->permit_number)
            ->assertSee($event->permit_number);

        $this->actingAs($security)->get(route('security.index', ['q' => 'Festival']))
            ->assertOk()
            ->assertSee($event->permit_number)
            ->assertDontSee($loading->permit_number)
            ->assertDontSee($work->permit_number);

        $this->actingAs($security)->get(route('security.index', ['q' => 'panel listrik']))
            ->assertOk()
            ->assertSee($work->permit_number)
            ->assertDontSee($event->permit_number);
    }

    public function test_only_security_can_download_non_revoked_official_letters(): void
    {
        $security = $this->security();
        $tr = User::factory()->create(['role' => 'validator', 'division' => 'TR', 'phone' => '081200009902']);
        $loading = $this->loadingPermit();
        $work = $this->workPermit(['status' => 'completed']);
        $event = $this->workPermit(['permit_kind' => 'event']);

        $this->actingAs($security)->get(route('security.permits.loading.letter', $loading))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($security)->get(route('security.permits.work.letter', $work))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($security)->get(route('security.permits.work.letter', $event))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($tr)->get(route('security.permits.loading.letter', $loading))->assertForbidden();

        $loading->update(['revoked_at' => now()]);
        $this->actingAs($security)->get(route('security.permits.loading.letter', $loading))->assertNotFound();

        $work->update(['status' => 'mep_review']);
        $this->actingAs($security)->get(route('security.permits.work.letter', $work))->assertNotFound();
    }

    private function security(): User
    {
        return User::factory()->create([
            'role' => 'validator',
            'division' => 'SEC',
            'phone' => '081200009901',
        ]);
    }

    private function loadingPermit(array $attributes = []): LoadingPermit
    {
        return LoadingPermit::query()->create(array_merge([
            'permit_number' => 'MBG/SEC/LOAD/'.Str::upper(Str::random(8)),
            'tenant_name' => 'Tenant Security',
            'applicant_name' => 'Pemohon Security',
            'applicant_phone' => '081211110005',
            'applicant_email' => 'security@example.test',
            'direction' => 'in',
            'movement_time' => '22:00',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'item_count' => 1,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian',
            'id_doc_path' => 'permits/id-docs/security-test.jpg',
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
            'contractor_name' => 'Kontraktor Security',
            'tenant_name' => 'Tenant Security',
            'applicant_name' => 'Penanggung Jawab',
            'applicant_phone' => '081234567890',
            'applicant_email' => 'security-work@example.test',
            'work_location' => 'Unit GF-12',
            'work_category' => 'other',
            'assigned_division' => 'MEP',
            'work_type' => 'Perbaikan instalasi',
            'work_schedule' => 'inside_store',
            'work_schedules' => ['inside_store'],
            'event_requirements' => [],
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'needs_water' => false,
            'security_deposit' => false,
            'deposit_required' => false,
            'id_doc_type' => 'ktp',
            'id_doc_path' => 'permits/work/id-docs/security-test.jpg',
            'status' => 'approved',
            'reviewed_at' => now(),
        ], $attributes));
    }
}
