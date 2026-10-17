<?php

namespace Tests\Feature;

use App\Models\LoadingPermit;
use App\Models\User;
use App\Models\WorkPermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PermitRevocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_admin_can_revoke_loading_permit_and_scanner_rejects_it(): void
    {
        $admin = $this->staff('admin');
        $permit = $this->loadingPermit();

        $this->actingAs($admin)->post(route('staff.permit-revocations.loading', $permit->permit_number), [
            'revocation_reason' => 'Akses area dibatalkan karena perubahan jadwal tenant.',
        ])->assertRedirect();

        $permit->refresh();
        $this->assertNotNull($permit->revoked_at);
        $this->assertSame($admin->id, $permit->revoked_by);
        $this->assertSame('Dicabut', $permit->status_label);

        $this->getJson(route('scanner.verify', ['token' => $permit->barcode_token]))
            ->assertStatus(410)
            ->assertJsonPath('status', 'revoked');

        $this->actingAs($admin)
            ->get(route('loading.letter', $permit->permit_number))
            ->assertNotFound();
    }

    public function test_unauthorized_staff_cannot_revoke_loading_permit(): void
    {
        $secretary = $this->staff('secretary');
        $permit = $this->loadingPermit();

        $this->actingAs($secretary)->post(route('staff.permit-revocations.loading', $permit->permit_number), [
            'revocation_reason' => 'Alasan pencabutan yang cukup panjang.',
        ])->assertForbidden();

        $this->assertNull($permit->fresh()->revoked_at);
    }

    public function test_only_the_assigned_work_division_or_admin_can_revoke_work_letter(): void
    {
        $permit = $this->workPermit();
        $tr = $this->staff('validator', 'TR');
        $mep = $this->staff('validator', 'MEP');

        $this->actingAs($tr)->post(route('staff.permit-revocations.work', $permit->public_token), [
            'revocation_reason' => 'Alasan pencabutan yang cukup panjang.',
        ])->assertForbidden();

        $this->actingAs($mep)->post(route('staff.permit-revocations.work', $permit->public_token), [
            'revocation_reason' => 'Pekerjaan dihentikan karena ketentuan keamanan area.',
        ])->assertRedirect();

        $permit->refresh();
        $this->assertNotNull($permit->revoked_at);
        $this->assertSame($mep->id, $permit->revoked_by);
        $this->assertDatabaseHas('work_permit_status_logs', [
            'work_permit_id' => $permit->id,
            'action' => 'permit_revoked',
        ]);

        $this->getJson(route('scanner.verify', ['token' => $permit->public_token]))
            ->assertStatus(410)
            ->assertJsonPath('status', 'revoked');

        $this->get(route('work-permits.letter', $permit->applicant_token))->assertNotFound();
    }

    public function test_revocation_requires_a_specific_reason(): void
    {
        $admin = $this->staff('admin');
        $permit = $this->loadingPermit();

        $this->actingAs($admin)
            ->from(route('admin.loading.show', $permit->permit_number))
            ->post(route('staff.permit-revocations.loading', $permit->permit_number), ['revocation_reason' => 'Singkat'])
            ->assertSessionHasErrors('revocation_reason');

        $this->assertNull($permit->fresh()->revoked_at);
    }

    private function loadingPermit(): LoadingPermit
    {
        return LoadingPermit::create([
            'permit_number' => 'MBG/SIK/TEST/'.Str::upper(Str::random(8)),
            'tenant_name' => 'Tenant Pengujian',
            'applicant_name' => 'Pemohon Pengujian',
            'applicant_phone' => '081200000001',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'movement_time' => '22:30',
            'item_count' => 1,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian',
            'id_doc_path' => 'permits/id-docs/test.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'approved',
            'barcode_token' => hash('sha256', Str::random(64)),
            'barcode_expires_at' => now()->addDay(),
        ]);
    }

    private function staff(string $role, ?string $division = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'division' => $division,
            'phone' => '0812'.fake()->unique()->numerify('########'),
        ]);
    }

    private function workPermit(): WorkPermit
    {
        return WorkPermit::create([
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
        ]);
    }
}
