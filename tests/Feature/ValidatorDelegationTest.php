<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireSensitiveActionConfirmation;
use App\Models\User;
use App\Models\ValidatorDelegation;
use App\Models\WorkPermit;
use App\Services\WorkPermitWorkflowNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ValidatorDelegationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(RequireSensitiveActionConfirmation::class);
    }

    public function test_admin_can_schedule_and_revoke_audited_temporary_access(): void
    {
        $admin = $this->user('admin');
        $validator = $this->user('validator', 'TR');

        $this->actingAs($admin)->post(route('admin.validator-delegations.store'), [
            'validator_id' => $validator->id,
            'division' => 'MEP',
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addDays(7)->toDateString(),
            'reason' => 'Menggantikan validator Engineering yang sedang cuti.',
            'current_password' => 'password',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $delegation = ValidatorDelegation::query()->sole();
        $this->assertTrue($validator->fresh()->canValidateDivision('MEP'));
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'created',
            'auditable_type' => ValidatorDelegation::class,
            'auditable_id' => $delegation->id,
            'actor_id' => $admin->id,
        ]);

        $this->actingAs($admin)->patch(route('admin.validator-delegations.revoke', $delegation), [
            'current_password' => 'password',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse($validator->fresh()->canValidateDivision('MEP'));
        $this->assertNotNull($delegation->fresh()->revoked_at);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'updated',
            'auditable_type' => ValidatorDelegation::class,
            'auditable_id' => $delegation->id,
            'actor_id' => $admin->id,
        ]);
    }

    public function test_invalid_or_overlapping_delegations_are_rejected(): void
    {
        $admin = $this->user('admin');
        $validator = $this->user('validator', 'TR');
        ValidatorDelegation::query()->create([
            'validator_id' => $validator->id,
            'division' => 'MEP',
            'starts_on' => today(),
            'ends_on' => today()->addDays(10),
            'reason' => 'Jadwal pengganti yang sudah tersimpan.',
            'created_by' => $admin->id,
        ]);

        $base = [
            'validator_id' => $validator->id,
            'starts_on' => today()->addDay()->toDateString(),
            'ends_on' => today()->addDays(5)->toDateString(),
            'reason' => 'Jadwal pengganti untuk kebutuhan operasional.',
            'current_password' => 'password',
        ];

        $this->actingAs($admin)->post(route('admin.validator-delegations.store'), [
            ...$base,
            'division' => 'MEP',
        ])->assertSessionHasErrors('starts_on');

        $this->actingAs($admin)->post(route('admin.validator-delegations.store'), [
            ...$base,
            'division' => 'TR',
        ])->assertSessionHasErrors('division');

        $this->actingAs($admin)->post(route('admin.validator-delegations.store'), [
            ...$base,
            'division' => 'SEC',
        ])->assertSessionHasErrors('division');

        $this->actingAs($admin)->post(route('admin.validator-delegations.store'), [
            ...$base,
            'division' => 'EP',
            'ends_on' => today()->addDays(92)->toDateString(),
        ])->assertSessionHasErrors('ends_on');
    }

    public function test_active_delegate_can_use_workspace_and_access_ends_automatically(): void
    {
        $validator = $this->user('validator', 'TR');
        $delegation = ValidatorDelegation::query()->create([
            'validator_id' => $validator->id,
            'division' => 'MEP',
            'starts_on' => today(),
            'ends_on' => today(),
            'reason' => 'Menggantikan pemeriksa Engineering hari ini.',
        ]);
        $permit = WorkPermit::query()->create($this->permitData());

        $this->assertTrue(User::query()->validatorForDivision('MEP')->whereKey($validator)->exists());
        $this->actingAs($validator)->get(route('mep.index'))
            ->assertOk()
            ->assertSee('Ruang kerja')
            ->assertSee('Tenant Relationship')
            ->assertSee('Engineering');
        $this->actingAs($validator)->get(route('staff.work-permits.show', [
            'token' => $permit->public_token,
            'workspace' => 'TR',
        ]))->assertOk()->assertSee('Tindakan Engineering')->assertSee('Simpan Keputusan');

        $delegation->update([
            'starts_on' => today()->subDays(2),
            'ends_on' => today()->subDay(),
        ]);

        $this->assertFalse($validator->fresh()->canValidateDivision('MEP'));
        $this->actingAs($validator)->get(route('mep.index'))->assertForbidden();
        $this->actingAs($validator)->get(route('staff.work-permits.show', $permit->public_token))->assertForbidden();
    }

    public function test_non_admin_cannot_manage_delegations(): void
    {
        $validator = $this->user('validator', 'TR');

        $this->actingAs($validator)->get(route('admin.validator-delegations.index'))->assertForbidden();
        $this->actingAs($validator)->post(route('admin.validator-delegations.store'), [
            'validator_id' => $validator->id,
            'division' => 'MEP',
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addDay()->toDateString(),
            'reason' => 'Tidak boleh membuat akses sendiri.',
            'current_password' => 'password',
        ])->assertForbidden();
    }

    public function test_active_delegate_receives_division_notifications(): void
    {
        Notification::fake();
        $validator = $this->user('validator', 'TR');
        ValidatorDelegation::query()->create([
            'validator_id' => $validator->id,
            'division' => 'MEP',
            'starts_on' => today(),
            'ends_on' => today()->addDay(),
            'reason' => 'Menggantikan pemeriksa Engineering sementara.',
        ]);
        $permit = WorkPermit::query()->create($this->permitData());

        app(WorkPermitWorkflowNotifier::class)->notifyDivision(
            $permit,
            'MEP',
            'Permohonan baru',
            'Permohonan perlu diperiksa.',
            'work',
            1,
            false,
        );

        $this->assertDatabaseHas('permit_notifications', [
            'user_id' => $validator->id,
            'work_permit_id' => $permit->id,
            'title' => 'Permohonan baru',
        ]);
    }

    private function user(string $role, ?string $division = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'division' => $division,
            'phone' => fake()->unique()->numerify('08##########'),
        ]);
    }

    /** @return array<string, mixed> */
    private function permitData(): array
    {
        return [
            'contractor_name' => 'Kontraktor Pengujian',
            'applicant_name' => 'Pemohon Pengujian',
            'applicant_phone' => '081234567890',
            'work_location' => 'Unit GF-12',
            'work_category' => 'other',
            'assigned_division' => 'MEP',
            'work_type' => 'Perbaikan instalasi',
            'work_schedule' => 'inside_store',
            'work_schedules' => ['inside_store'],
            'start_date' => today(),
            'end_date' => today()->addDay(),
            'needs_water' => false,
            'security_deposit' => false,
            'id_doc_type' => 'ktp',
            'id_doc_path' => 'permits/work/id-docs/test.jpg',
            'status' => 'mep_review',
        ];
    }
}
