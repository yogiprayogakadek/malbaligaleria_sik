<?php

namespace Tests\Feature;

use App\Models\LoadingPermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanner_page_uses_local_assets_and_offers_manual_verification(): void
    {
        $this->get(route('scanner.index'))
            ->assertOk()
            ->assertSee('Aktifkan kamera')
            ->assertSee('Tempel token atau tautan QR')
            ->assertDontSee('cdn.jsdelivr.net');
    }

    public function test_scanner_rejects_malformed_tokens_without_querying_a_permit(): void
    {
        $this->getJson(route('scanner.verify', ['token' => '../invalid-token']))
            ->assertBadRequest()
            ->assertJson([
                'valid' => false,
                'status' => 'invalid',
            ])
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_scanner_returns_an_active_approved_permit(): void
    {
        $permit = $this->createPermit();

        $this->getJson(route('scanner.verify', ['token' => $permit->barcode_token]))
            ->assertOk()
            ->assertJson([
                'valid' => true,
                'status' => 'active',
                'permit_number' => $permit->permit_number,
                'tenant_name' => $permit->tenant_name,
                'movement_time' => '22:30 WITA',
            ]);
    }

    public function test_scanner_rejects_pending_and_expired_permits(): void
    {
        $pending = $this->createPermit(['status' => 'pending']);
        $expired = $this->createPermit(['barcode_expires_at' => now()->subMinute()]);

        $this->getJson(route('scanner.verify', ['token' => $pending->barcode_token]))
            ->assertUnprocessable()
            ->assertJsonPath('status', 'not_approved');

        $this->getJson(route('scanner.verify', ['token' => $expired->barcode_token]))
            ->assertUnprocessable()
            ->assertJsonPath('status', 'expired');
    }

    private function createPermit(array $attributes = []): LoadingPermit
    {
        return LoadingPermit::create(array_merge([
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
        ], $attributes));
    }
}
