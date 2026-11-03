<?php

namespace Tests\Feature;

use App\Models\LoadingPermit;
use App\Models\ScannerVerificationLog;
use App\Models\SecurityGateEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_security_reconciliation(): void
    {
        $admin = $this->user('admin');
        $validator = $this->user('validator', 'TR');

        $this->get(route('admin.security-reconciliation.index'))->assertRedirect();
        $this->actingAs($validator)->get(route('admin.security-reconciliation.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.security-reconciliation.index'))->assertOk();
    }

    public function test_report_finds_unscanned_open_and_outside_window_access(): void
    {
        $admin = $this->user('admin');
        $unscanned = $this->permit('Belum Dipindai');
        $unscanned->update([
            'start_date' => today()->subDay(),
            'end_date' => today(),
        ]);
        $scanned = $this->permit('Sudah Dipindai');
        $this->markScanned($scanned);

        $open = $this->permit('Check In Terbuka');
        $this->markScanned($open);
        $this->gateEvent($open, 'check_in', today()->setTime(22, 30));

        $closed = $this->permit('Sudah Check Out');
        $this->markScanned($closed);
        $this->gateEvent($closed, 'check_in', today()->setTime(22, 30));
        $this->gateEvent($closed, 'check_out', today()->setTime(23, 0));

        $outside = $this->permit('Di Luar Waktu');
        $this->markScanned($outside);
        $this->gateEvent($outside, 'check_in', today()->setTime(14, 0));
        $future = $this->permit('Belum Mulai');
        $future->update([
            'start_date' => today()->addDay(),
            'end_date' => today()->addDays(2),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.security-reconciliation.index', [
            'date_from' => today()->subDay()->toDateString(),
            'date_to' => today()->addDay()->toDateString(),
        ]))->assertOk();

        $response->assertSee($unscanned->permit_number)
            ->assertDontSee($scanned->permit_number)
            ->assertSee($open->permit_number)
            ->assertDontSee($closed->permit_number)
            ->assertSee($outside->permit_number)
            ->assertDontSee($future->permit_number)
            ->assertSee('Di luar waktu izin');
    }

    public function test_reconciliation_range_is_limited(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->get(route('admin.security-reconciliation.index', [
            'date_from' => today()->subDays(100)->toDateString(),
            'date_to' => today()->toDateString(),
        ]))->assertSessionHasErrors('date_to');
    }

    private function user(string $role, ?string $division = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'division' => $division,
            'phone' => fake()->unique()->numerify('08##########'),
        ]);
    }

    private function permit(string $tenant): LoadingPermit
    {
        return LoadingPermit::query()->create([
            'permit_number' => 'MBG/SIK/TEST/'.Str::upper(Str::random(8)),
            'tenant_name' => $tenant,
            'applicant_name' => 'Pemohon Pengujian',
            'applicant_phone' => '081200000001',
            'direction' => 'in',
            'start_date' => today(),
            'end_date' => today()->addDay(),
            'movement_time' => '22:30',
            'item_count' => 1,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian',
            'id_doc_path' => 'permits/id-docs/test.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'approved',
            'reviewed_at' => now(),
            'barcode_token' => hash('sha256', Str::random(64)),
            'barcode_expires_at' => today()->addDay()->endOfDay(),
        ]);
    }

    private function gateEvent(LoadingPermit $permit, string $event, mixed $occurredAt): SecurityGateEvent
    {
        return SecurityGateEvent::query()->create([
            'permit_type' => 'loading',
            'permit_id' => $permit->id,
            'event' => $event,
            'token_hash' => hash('sha256', $permit->barcode_token),
            'occurred_at' => $occurredAt,
        ]);
    }

    private function markScanned(LoadingPermit $permit): void
    {
        ScannerVerificationLog::query()->create([
            'token_hash' => hash('sha256', $permit->barcode_token),
            'permit_type' => 'loading',
            'permit_id' => $permit->id,
            'result' => 'active',
            'status_code' => 200,
        ]);
    }
}
