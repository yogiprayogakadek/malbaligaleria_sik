<?php

namespace Tests\Feature;

use App\Models\LoadingPermit;
use App\Models\User;
use App\Models\WorkPermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PermitAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_validator_can_claim_and_explicitly_take_over_a_loading_permit(): void
    {
        $first = $this->validator('TR');
        $second = $this->validator('TR');
        $permit = $this->loadingPermit();

        $this->actingAs($first)
            ->post(route('tr.assignment', $permit->permit_number))
            ->assertRedirect();
        $this->assertSame($first->id, $permit->fresh()->assigned_validator_id);

        $this->actingAs($second)
            ->post(route('tr.assignment', $permit->permit_number))
            ->assertStatus(409);
        $this->actingAs($second)
            ->post(route('tr.assignment', $permit->permit_number), [
                'takeover' => '1',
                'takeover_reason' => 'Pergantian jadwal tugas validator.',
            ])
            ->assertRedirect();

        $this->assertSame($second->id, $permit->fresh()->assigned_validator_id);
        $this->assertSame('Pergantian jadwal tugas validator.', $permit->fresh()->assignment_note);
    }

    public function test_validator_cannot_process_a_permit_assigned_to_another_validator(): void
    {
        Notification::fake();
        $owner = $this->validator('TR');
        $other = $this->validator('TR');
        $permit = $this->loadingPermit();
        $permit->forceFill(['assigned_validator_id' => $owner->id, 'assigned_at' => now()])->save();

        $this->actingAs($other)
            ->post(route('tr.approve', $permit->permit_number))
            ->assertStatus(409);

        $this->assertSame('pending', $permit->fresh()->status);
        $this->actingAs($owner)
            ->post(route('tr.approve', $permit->permit_number))
            ->assertRedirect(route('tr.index'));
        $this->assertSame('approved', $permit->fresh()->status);
        $this->assertNull($permit->fresh()->assigned_validator_id);
    }

    public function test_work_assignment_is_limited_to_the_current_action_division(): void
    {
        $mep = $this->validator('MEP');
        $tr = $this->validator('TR');
        $permit = $this->workPermit();

        $this->actingAs($tr)
            ->post(route('staff.work-permits.assignment', $permit->public_token))
            ->assertForbidden();
        $this->actingAs($mep)
            ->post(route('staff.work-permits.assignment', $permit->public_token))
            ->assertRedirect();

        $this->assertSame($mep->id, $permit->fresh()->assigned_validator_id);
        $this->assertDatabaseHas('work_permit_status_logs', [
            'work_permit_id' => $permit->id,
            'action' => 'assignment_claimed',
        ]);
    }

    public function test_work_decision_is_blocked_until_the_assignee_handles_or_releases_it(): void
    {
        Notification::fake();
        $owner = $this->validator('MEP');
        $other = $this->validator('MEP');
        $permit = $this->workPermit();
        $permit->forceFill(['assigned_validator_id' => $owner->id, 'assigned_at' => now()])->save();

        $this->actingAs($other)
            ->post(route('mep.work-permits.deposit', $permit->public_token), [
                'deposit_required' => '0',
            ])
            ->assertStatus(409);
        $this->assertSame('mep_review', $permit->fresh()->status);

        $this->actingAs($owner)
            ->post(route('mep.work-permits.deposit', $permit->public_token), [
                'deposit_required' => '0',
            ])
            ->assertRedirect();

        $this->assertSame('approved', $permit->fresh()->status);
        $this->assertNull($permit->fresh()->assigned_validator_id);
    }

    public function test_admin_can_assign_only_an_active_validator_from_the_required_division(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'division' => null, 'phone' => '081111111111']);
        $tr = $this->validator('TR');
        $mep = $this->validator('MEP');
        $inactiveTr = $this->validator('TR', false);
        $permit = $this->loadingPermit();

        $this->actingAs($admin)
            ->put(route('admin.loading.assignment', $permit->permit_number), ['validator_id' => $mep->id])
            ->assertSessionHasErrors('validator_id');
        $this->actingAs($admin)
            ->put(route('admin.loading.assignment', $permit->permit_number), ['validator_id' => $inactiveTr->id])
            ->assertSessionHasErrors('validator_id');
        $this->actingAs($admin)
            ->put(route('admin.loading.assignment', $permit->permit_number), ['validator_id' => $tr->id])
            ->assertRedirect();

        $this->assertSame($tr->id, $permit->fresh()->assigned_validator_id);
    }

    public function test_admin_sidebar_has_an_independent_scrollable_menu(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'division' => null, 'phone' => '082222222222']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('class="sidebar-nav"', false);

        $styles = file_get_contents(public_path('admin.css'));
        $this->assertStringContainsString('.admin-shell .sidebar-nav', $styles);
        $this->assertStringContainsString('overflow-y: auto', $styles);
    }

    private function validator(string $division, bool $active = true): User
    {
        return User::factory()->create([
            'role' => 'validator',
            'division' => $division,
            'is_active' => $active,
            'phone' => fake()->unique()->numerify('08##########'),
        ]);
    }

    private function loadingPermit(): LoadingPermit
    {
        return LoadingPermit::create([
            'permit_number' => 'MBG/SIK/TEST/'.fake()->unique()->numerify('####'),
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
            'end_date' => now()->addDays(2)->toDateString(),
            'needs_water' => false,
            'security_deposit' => false,
            'id_doc_type' => 'ktp',
            'id_doc_path' => 'permits/work/id-docs/test.jpg',
            'status' => 'mep_review',
        ]);
    }
}
