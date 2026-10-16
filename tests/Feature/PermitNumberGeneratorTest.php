<?php

namespace Tests\Feature;

use App\Models\LoadingPermit;
use App\Models\WorkPermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_loading_number_uses_an_atomic_sequence_seeded_from_existing_numbers(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 16));
        $this->permit('MBG/SIK/X/0007');
        $this->permit('MBG/SIK/X/0012');
        $this->permit('MBG/SIK/IX/0099');

        $this->assertSame('MBG/SIK/X/0013', LoadingPermit::generatePermitNumber());
        $this->assertSame('MBG/SIK/X/0014', LoadingPermit::generatePermitNumber());
        $this->assertDatabaseHas('permit_number_sequences', [
            'permit_type' => 'loading',
            'period' => 'month-10',
            'current_value' => 14,
        ]);
    }

    public function test_work_number_uses_roman_month_year_and_a_database_sequence(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 16));
        WorkPermit::create(array_merge($this->workPermitData(), [
            'permit_number' => 'MBG/SIK-KERJA/202609/LEGACY001',
        ]));

        $first = WorkPermit::create($this->workPermitData());
        $second = WorkPermit::create($this->workPermitData());

        $this->assertSame('MBG/SIK/X/2026/0002', $first->permit_number);
        $this->assertSame('MBG/SIK/X/2026/0003', $second->permit_number);
        $this->assertDatabaseHas('permit_number_sequences', [
            'permit_type' => 'work',
            'period' => 'global',
            'current_value' => 3,
        ]);
    }

    private function permit(string $number): LoadingPermit
    {
        return LoadingPermit::create([
            'permit_number' => $number,
            'tenant_name' => 'Tenant Sequence',
            'applicant_name' => 'Pemohon Sequence',
            'applicant_phone' => '081234567890',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'movement_time' => '22:30',
            'item_count' => 1,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian sequence',
            'id_doc_path' => 'permits/id-docs/sequence.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'pending',
        ]);
    }

    /** @return array<string, mixed> */
    private function workPermitData(): array
    {
        return [
            'contractor_name' => 'Kontraktor Sequence',
            'applicant_name' => 'Pemohon Sequence',
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
            'id_doc_path' => 'permits/work/id-docs/sequence.jpg',
            'status' => 'mep_review',
        ];
    }
}
