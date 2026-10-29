<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureWebsiteOperating;
use App\Http\Middleware\RequireSensitiveActionConfirmation;
use App\Models\OperationTrace;
use App\Models\User;
use App\Models\WorkPermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OperationTraceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureWebsiteOperating::class);
        Notification::fake();
        Queue::fake();
    }

    public function test_event_submission_records_process_steps_and_database_time(): void
    {
        $token = $this->get(route('event-permits.create'))->viewData('submissionToken');

        $this->post(route('event-permits.store'), [
            'submission_token' => $token,
            'event_type' => 'organizer',
            'organizer_name' => 'EO Timeline Bali',
            'applicant_name' => 'Made Timeline',
            'applicant_phone' => '081234567890',
            'applicant_email' => 'timeline@example.com',
            'work_location' => 'Atrium MBG',
            'work_category' => 'mall_event',
            'work_type' => 'Penyelenggaraan acara',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'notes' => 'Pengujian timeline proses pengajuan izin event.',
            'includes_loading_material' => '0',
            'terms_accepted' => '1',
        ])->assertRedirect();

        $permit = WorkPermit::query()->where('permit_kind', 'event')->sole();
        $trace = OperationTrace::query()->sole();
        $stepNames = collect($trace->steps)->pluck('name');

        $this->assertSame('event_permit_submission', $trace->operation);
        $this->assertSame('success', $trace->status);
        $this->assertSame($permit->id, $trace->work_permit_id);
        $this->assertGreaterThan(0, $trace->query_count);
        $this->assertGreaterThanOrEqual(0, $trace->query_ms);
        $this->assertTrue($stepNames->contains('Transaksi data permohonan'));
        $this->assertTrue($stepNames->contains('Notifikasi staf'));
        $this->assertTrue($stepNames->contains('Notifikasi pemohon'));
    }

    public function test_only_admin_can_view_process_timelines(): void
    {
        $this->withoutMiddleware(RequireSensitiveActionConfirmation::class);
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081288811101']);
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081288811102']);
        $trace = OperationTrace::query()->create([
            'request_id' => (string) str()->ulid(),
            'operation' => 'loading_submission',
            'route_name' => 'loading.store',
            'status' => 'success',
            'response_status' => 302,
            'total_ms' => 1250,
            'query_count' => 8,
            'query_ms' => 40,
            'upload_count' => 1,
            'upload_bytes' => 1024,
            'slowest_step' => 'Pemeriksaan keamanan dokumen',
            'slowest_step_ms' => 900,
            'steps' => [
                ['name' => 'Pemeriksaan keamanan dokumen', 'duration_ms' => 900, 'status' => 'success'],
            ],
            'started_at' => now(),
        ]);

        $this->actingAs($tenant)->get(route('admin.operation-traces.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.operation-traces.index'))
            ->assertOk()
            ->assertSee('Pemeriksaan keamanan dokumen')
            ->assertSee('1,25 detik');
        $this->get(route('admin.operation-traces.show', $trace))
            ->assertOk()
            ->assertSee('Paling lambat')
            ->assertSee('900,00 ms');
    }

    public function test_failed_validation_is_recorded_without_form_values(): void
    {
        $token = $this->get(route('event-permits.create'))->viewData('submissionToken');

        $this->post(route('event-permits.store'), [
            'submission_token' => $token,
            'event_type' => 'organizer',
        ])->assertSessionHasErrors();

        $trace = OperationTrace::query()->sole();
        $failedStep = collect($trace->steps)->firstWhere('status', 'failed');

        $this->assertSame('failed', $trace->status);
        $this->assertSame('ValidationException', $failedStep['error']);
        $this->assertArrayNotHasKey('payload', $trace->toArray());
        $this->assertStringNotContainsString('organizer', json_encode($trace->steps, JSON_THROW_ON_ERROR));
    }
}
