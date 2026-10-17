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

    public function test_assignment_routes_are_removed(): void
    {
        $this->assertFalse(app('router')->has('tr.assignment'));
        $this->assertFalse(app('router')->has('staff.work-permits.assignment'));
        $this->assertFalse(app('router')->has('admin.loading.assignment'));
        $this->assertFalse(app('router')->has('admin.work-permits.assignment'));
    }

    public function test_any_active_validator_from_the_required_division_can_process_a_loading_permit(): void
    {
        Notification::fake();
        $owner = $this->validator('TR');
        $other = $this->validator('TR');
        $permit = $this->loadingPermit();
        $permit->forceFill(['assigned_validator_id' => $owner->id, 'assigned_at' => now()])->save();

        $this->actingAs($other)
            ->post(route('tr.approve', $permit->permit_number))
            ->assertRedirect(route('tr.index'));

        $this->assertSame('approved', $permit->fresh()->status);
        $this->assertNull($permit->fresh()->assigned_validator_id);
    }

    public function test_validator_from_another_division_cannot_process_a_work_permit(): void
    {
        $mep = $this->validator('MEP');
        $tr = $this->validator('TR');
        $permit = $this->workPermit();

        $this->actingAs($tr)
            ->post(route('mep.work-permits.deposit', $permit->public_token), ['deposit_required' => '0'])
            ->assertForbidden();

        $this->assertSame('mep_review', $permit->fresh()->status);
    }

    public function test_any_active_mep_validator_can_process_a_mep_permit(): void
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
            ->assertRedirect();

        $this->assertSame('approved', $permit->fresh()->status);
        $this->assertNull($permit->fresh()->assigned_validator_id);
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
