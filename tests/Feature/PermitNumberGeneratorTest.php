<?php

namespace Tests\Feature;

use App\Models\LoadingPermit;
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
}
