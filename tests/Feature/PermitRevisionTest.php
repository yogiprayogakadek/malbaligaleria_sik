<?php

namespace Tests\Feature;

use App\Events\LoadingPermitSubmitted;
use App\Models\LoadingPermit;
use App\Models\User;
use App\Models\WorkPermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PermitRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_revision_field_picker_keeps_checkbox_and_labels_aligned(): void
    {
        $styles = file_get_contents(public_path('styles.css'));
        $component = file_get_contents(resource_path('views/components/permit-revision-request.blade.php'));

        $this->assertIsString($styles);
        $this->assertIsString($component);
        $this->assertStringContainsString('grid-template-columns: 20px minmax(0, 1fr);', $styles);
        $this->assertStringContainsString('align-items: start;', $styles);
        $this->assertStringContainsString('min-height: 64px;', $styles);
        $this->assertStringContainsString('aria-expanded=', $component);
        $this->assertStringContainsString("classList.toggle('is-selected', checkbox.checked)", $component);
    }

    public function test_tr_can_request_structured_loading_revisions(): void
    {
        Notification::fake();
        $validator = $this->validator('TR');
        $permit = $this->loadingPermit();

        $this->actingAs($validator)->post(route('staff.permit-revisions.store', ['loading', $permit->id]), [
            'fields' => ['applicant_phone', 'item_description'],
            'reasons' => [
                'applicant_phone' => 'Nomor WhatsApp tidak dapat dihubungi.',
                'item_description' => 'Jenis dan jumlah barang perlu dijelaskan lebih rinci.',
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $permit->refresh();
        $this->assertSame('revision_required', $permit->status);
        $this->assertSame('pending', $permit->revision_return_status);
        $this->assertSame($validator->id, $permit->reviewed_by);
        $this->assertDatabaseCount('permit_revisions', 2);
        $this->assertDatabaseHas('permit_revisions', [
            'revisable_type' => LoadingPermit::class,
            'revisable_id' => $permit->id,
            'field_key' => 'applicant_phone',
            'resolved_at' => null,
        ]);
    }

    public function test_revision_requires_a_reason_for_every_selected_field(): void
    {
        $validator = $this->validator('TR');
        $permit = $this->loadingPermit();

        $this->actingAs($validator)->from(route('tr.show', $permit->permit_number))
            ->post(route('staff.permit-revisions.store', ['loading', $permit->id]), [
                'fields' => ['applicant_phone'],
                'reasons' => ['applicant_phone' => 'Pendek'],
            ])
            ->assertRedirect(route('tr.show', $permit->permit_number))
            ->assertSessionHasErrors('reasons.applicant_phone');

        $this->assertSame('pending', $permit->fresh()->status);
        $this->assertDatabaseCount('permit_revisions', 0);
    }

    public function test_loading_revision_page_requires_owner_or_signed_url(): void
    {
        $permit = $this->loadingPermit(['status' => 'revision_required', 'revision_return_status' => 'pending']);
        $this->addRevision($permit, 'item_description');

        $this->get(route('loading.revision.edit', $permit->permit_number))->assertForbidden();

        $url = URL::temporarySignedRoute('loading.revision.edit', now()->addMinutes(10), [
            'permitNumber' => $permit->permit_number,
        ]);
        $this->get($url)
            ->assertOk()
            ->assertSee('Keterangan barang')
            ->assertSee('Jenis barang belum cukup jelas.');
    }

    public function test_loading_applicant_can_only_change_requested_fields(): void
    {
        Event::fake([LoadingPermitSubmitted::class]);
        Notification::fake();
        $permit = $this->loadingPermit([
            'status' => 'revision_required',
            'revision_return_status' => 'pending',
            'direction' => 'in',
            'item_description' => 'Deskripsi lama',
        ]);
        $this->addRevision($permit, 'item_description');
        $url = URL::temporarySignedRoute('loading.revision.update', now()->addMinutes(10), [
            'permitNumber' => $permit->permit_number,
        ]);

        $this->post($url, [
            'item_description' => 'Deskripsi baru yang sudah lengkap',
            'direction' => 'out',
            'tenant_name' => 'Tenant yang dimanipulasi',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $permit->refresh();
        $this->assertSame('pending', $permit->status);
        $this->assertSame('Deskripsi baru yang sudah lengkap', $permit->item_description);
        $this->assertSame('in', $permit->direction);
        $this->assertSame('Tenant Pengujian', $permit->tenant_name);
        $this->assertNotNull($permit->revisions()->first()->resolved_at);

        $this->post($url, ['item_description' => 'Pengiriman kedua'])->assertStatus(409);
    }

    public function test_validator_from_another_division_cannot_request_work_revision(): void
    {
        $permit = $this->workPermit(['assigned_division' => 'TR', 'status' => 'tr_review']);
        $mep = $this->validator('MEP');

        $this->actingAs($mep)->post(route('staff.permit-revisions.store', ['work', $permit->id]), [
            'fields' => ['work_type'],
            'reasons' => ['work_type' => 'Uraian pekerjaan perlu diperjelas.'],
        ])->assertForbidden();

        $this->assertSame('tr_review', $permit->fresh()->status);
        $this->assertDatabaseCount('permit_revisions', 0);
    }

    public function test_admin_and_inactive_validator_cannot_request_a_revision(): void
    {
        $permit = $this->loadingPermit();
        $payload = [
            'fields' => ['item_description'],
            'reasons' => ['item_description' => 'Keterangan barang perlu dibuat lebih rinci.'],
        ];
        $admin = User::factory()->create([
            'role' => 'admin',
            'division' => null,
            'phone' => '081299999999',
        ]);
        $inactiveValidator = $this->validator('TR');
        $inactiveValidator->update(['is_active' => false]);

        $this->actingAs($admin)
            ->post(route('staff.permit-revisions.store', ['loading', $permit->id]), $payload)
            ->assertForbidden();
        $this->actingAs($inactiveValidator)
            ->post(route('staff.permit-revisions.store', ['loading', $permit->id]), $payload)
            ->assertRedirect(route('login'));

        $this->assertSame('pending', $permit->fresh()->status);
        $this->assertDatabaseCount('permit_revisions', 0);
    }

    public function test_work_applicant_can_update_requested_workers_but_not_other_fields(): void
    {
        Notification::fake();
        $validator = $this->validator('TR');
        $permit = $this->workPermit(['assigned_division' => 'TR', 'status' => 'tr_review']);
        $permit->workers()->create(['name' => 'Nama Lama', 'identity_number' => 'OLD-1', 'position' => 1]);

        $this->actingAs($validator)->post(route('staff.permit-revisions.store', ['work', $permit->id]), [
            'fields' => ['workers'],
            'reasons' => ['workers' => 'Daftar pekerja perlu diperbarui sesuai tim yang hadir.'],
        ])->assertRedirect();
        auth()->logout();

        $this->post(route('work-permits.revision.update', $permit->applicant_token), [
            'workers' => [
                ['name' => 'Pekerja Baru', 'identity_number' => 'NEW-1'],
                ['name' => '', 'identity_number' => ''],
            ],
            'work_type' => 'Nilai ini tidak boleh diterapkan',
        ])->assertRedirect(route('work-permits.status', $permit->applicant_token));

        $permit->refresh()->load('workers');
        $this->assertSame('tr_review', $permit->status);
        $this->assertSame('Pekerjaan pengujian', $permit->work_type);
        $this->assertCount(2, $permit->workers);
        $this->assertSame('Pekerja Baru', $permit->workers->first()->name);
        $this->assertNull($permit->workers->last()->name);
        $this->assertDatabaseHas('work_permit_status_logs', [
            'work_permit_id' => $permit->id,
            'action' => 'revision_submitted',
            'to_status' => 'tr_review',
        ]);
    }

    public function test_requested_category_change_routes_work_back_to_the_correct_division(): void
    {
        Notification::fake();
        $validator = $this->validator('TR');
        $permit = $this->workPermit(['work_category' => 'general_cleaning', 'assigned_division' => 'TR', 'status' => 'tr_review']);

        $this->actingAs($validator)->post(route('staff.permit-revisions.store', ['work', $permit->id]), [
            'fields' => ['work_category'],
            'reasons' => ['work_category' => 'Kategori tidak sesuai dengan kegiatan yang dijelaskan.'],
        ])->assertRedirect();
        auth()->logout();

        $this->post(route('work-permits.revision.update', $permit->applicant_token), [
            'work_category' => 'kol_event',
        ])->assertRedirect();

        $permit->refresh();
        $this->assertSame('EP', $permit->assigned_division);
        $this->assertSame('ep_review', $permit->status);
    }

    private function addRevision(LoadingPermit|WorkPermit $permit, string $field): void
    {
        $permit->revisions()->create([
            'batch_id' => '70c53a28-7b76-49c4-a5a0-2cf63d8e04d3',
            'field_key' => $field,
            'reason' => 'Jenis barang belum cukup jelas.',
        ]);
    }

    private function validator(string $division): User
    {
        return User::factory()->create([
            'role' => 'validator',
            'division' => $division,
            'phone' => '0812000000'.random_int(10, 99),
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function loadingPermit(array $overrides = []): LoadingPermit
    {
        return LoadingPermit::create(array_merge([
            'permit_number' => 'MBG/SIK/X/REV1',
            'tenant_name' => 'Tenant Pengujian',
            'applicant_name' => 'Pemohon Pengujian',
            'applicant_phone' => '081234567890',
            'applicant_email' => null,
            'direction' => 'in',
            'movement_time' => '22:00',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'item_count' => 2,
            'item_unit' => 'koli',
            'item_description' => 'Deskripsi lama',
            'id_doc_path' => 'permits/id-docs/test.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'pending',
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function workPermit(array $overrides = []): WorkPermit
    {
        return WorkPermit::create(array_merge([
            'contractor_name' => 'Kontraktor Pengujian',
            'applicant_name' => 'Pemohon Pengujian',
            'applicant_phone' => '081234567890',
            'applicant_email' => null,
            'work_location' => 'Unit GF-10',
            'work_category' => 'general_cleaning',
            'assigned_division' => 'TR',
            'work_type' => 'Pekerjaan pengujian',
            'work_schedule' => 'inside_store',
            'work_schedules' => ['inside_store'],
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'needs_water' => false,
            'security_deposit' => false,
            'deposit_required' => false,
            'id_doc_path' => 'permits/work/id-docs/test.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'tr_review',
        ], $overrides));
    }
}
