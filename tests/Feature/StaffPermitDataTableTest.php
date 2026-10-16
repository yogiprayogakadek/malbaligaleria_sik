<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkPermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPermitDataTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_validator_work_queues_use_the_shared_data_table_controls(): void
    {
        $routes = [
            ['MEP', 'mep.index'],
            ['TR', 'tr.work-permits.index'],
            ['EP', 'ep.index'],
            ['FIN', 'finance.index'],
        ];

        foreach ($routes as [$division, $route]) {
            $user = User::factory()->create([
                'role' => 'validator',
                'division' => $division,
                'phone' => fake()->unique()->numerify('08##########'),
            ]);

            $this->actingAs($user)
                ->get(route($route, ['per_page' => 20]))
                ->assertOk()
                ->assertSee('data-staff-data-table', false)
                ->assertSee('data-staff-per-page', false)
                ->assertSee('data-staff-search', false)
                ->assertSee('data-staff-export', false);
        }
    }

    public function test_mep_detail_hides_assignment_and_conditional_forms_by_default(): void
    {
        $mep = User::factory()->create([
            'role' => 'validator',
            'division' => 'MEP',
            'phone' => '081234567899',
        ]);
        $permit = WorkPermit::create($this->permitData());

        $response = $this->actingAs($mep)
            ->get(route('staff.work-permits.show', $permit->public_token))
            ->assertOk()
            ->assertDontSee('Ambil permohonan')
            ->assertSee('Tidak ada perbaikan');

        $this->assertMatchesRegularExpression('/data-deposit-amount\s+hidden/', $response->getContent());
        $this->assertMatchesRegularExpression('/data-revision-options\s+hidden/', $response->getContent());
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
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'needs_water' => false,
            'security_deposit' => false,
            'id_doc_type' => 'ktp',
            'id_doc_path' => 'permits/work/id-docs/test.jpg',
            'status' => 'mep_review',
        ];
    }
}
