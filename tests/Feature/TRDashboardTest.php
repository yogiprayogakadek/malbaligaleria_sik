<?php

namespace Tests\Feature;

use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TRDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_tr_validator_can_open_the_dedicated_dashboard(): void
    {
        $validator = User::factory()->create([
            'phone' => '082200000001',
            'role' => 'validator',
            'division' => 'TR',
        ]);

        $response = $this->actingAs($validator)->get(route('tr.index'));

        $response->assertOk();
        $response->assertSee('appShell', false);
        $response->assertSee('Antrean verifikasi loading');
        $response->assertDontSee('Ringkasan permohonan');
        $response->assertSee('validatorNotifBtn', false);
        $response->assertSee('manifest.webmanifest', false);
        $response->assertSee('data-push-toggle', false);
        $response->assertDontSee(config('webpush.vapid.private_key'), false);
    }

    public function test_summary_cards_only_appear_on_the_all_filter(): void
    {
        $validator = User::factory()->create([
            'phone' => '082200000005',
            'role' => 'validator',
            'division' => 'TR',
        ]);

        $this->actingAs($validator)
            ->get(route('tr.index', ['status' => 'all']))
            ->assertOk()
            ->assertSee('Ringkasan permohonan')
            ->assertDontSee('Filter status permohonan');

        $this->actingAs($validator)
            ->get(route('tr.index', ['status' => 'approved']))
            ->assertOk()
            ->assertDontSee('Ringkasan permohonan')
            ->assertDontSee('Filter status permohonan');
    }

    public function test_tenant_cannot_open_the_tr_dashboard(): void
    {
        $tenant = User::factory()->create([
            'phone' => '082200000002',
            'role' => 'tenant',
            'division' => null,
        ]);

        $this->actingAs($tenant)
            ->get(route('tr.index'))
            ->assertForbidden();
    }

    public function test_authenticated_tr_validator_is_redirected_from_login_to_dashboard(): void
    {
        $validator = User::factory()->create([
            'phone' => '082200000003',
            'role' => 'validator',
            'division' => 'TR',
        ]);

        $this->actingAs($validator)
            ->get(route('login'))
            ->assertRedirect(route('tr.index'));
    }

    public function test_tr_validator_is_redirected_from_tenant_home_to_dashboard(): void
    {
        $validator = User::factory()->create([
            'phone' => '082200000004',
            'role' => 'validator',
            'division' => 'TR',
        ]);

        $this->actingAs($validator)
            ->get(route('portal.dashboard'))
            ->assertRedirect(route('tr.index'));
    }

    public function test_validator_can_open_and_mark_own_notification_as_read(): void
    {
        $validator = User::factory()->create([
            'phone' => '082200000006',
            'role' => 'validator',
            'division' => 'TR',
        ]);
        $permit = LoadingPermit::create([
            'permit_number' => 'MBG/SIK/TEST/0001',
            'tenant_name' => 'Tenant Pengujian',
            'applicant_name' => 'Pemohon Pengujian',
            'applicant_phone' => '081200000001',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'item_count' => 1,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian',
            'id_doc_path' => 'permits/id-docs/test.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'pending',
        ]);
        $notification = PermitNotification::create([
            'user_id' => $validator->id,
            'permit_id' => $permit->id,
            'type' => 'pending_review',
            'title' => 'Permohonan Baru',
            'body' => 'Permohonan menunggu pemeriksaan.',
        ]);

        $this->actingAs($validator)
            ->post(route('tr.notifications.read', $notification))
            ->assertRedirect(route('tr.show', $permit->permit_number));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_detail_page_has_a_back_to_queue_action(): void
    {
        $validator = User::factory()->create([
            'phone' => '082200000007',
            'role' => 'validator',
            'division' => 'TR',
        ]);
        $permit = LoadingPermit::create([
            'permit_number' => 'MBG/SIK/TEST/0002',
            'tenant_name' => 'Tenant Pengujian',
            'applicant_name' => 'Pemohon Pengujian',
            'applicant_phone' => '081200000002',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'item_count' => 1,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian',
            'id_doc_path' => 'permits/id-docs/test.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'pending',
        ]);

        $this->actingAs($validator)
            ->get(route('tr.show', $permit->permit_number))
            ->assertOk()
            ->assertSee('Kembali ke antrean')
            ->assertSee(route('tr.index', ['status' => 'pending']), false);
    }
}
