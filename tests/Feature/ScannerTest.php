<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureWebsiteOperating;
use App\Models\LoadingPermit;
use App\Models\ScannerSetting;
use App\Models\WorkPermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureWebsiteOperating::class);
    }

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

    public function test_scanner_verifies_an_approved_work_permit_with_a_mixed_case_token(): void
    {
        $permit = WorkPermit::create([
            'public_token' => str_repeat('Ab', 32),
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

        $this->getJson(route('scanner.verify', ['token' => $permit->public_token]))
            ->assertOk()
            ->assertJson([
                'valid' => true,
                'permit_type' => 'work',
                'permit_number' => $permit->permit_number,
            ]);
    }

    public function test_scanner_remains_public_without_authentication(): void
    {
        $this->assertGuest();

        $this->get(route('scanner.index'))->assertOk();
    }

    public function test_geofence_requires_an_accurate_location_inside_the_configured_radius(): void
    {
        ScannerSetting::query()->findOrFail(1)->update([
            'access_mode' => ScannerSetting::MODE_GEOFENCE,
            'latitude' => -8.7212345,
            'longitude' => 115.1845678,
            'radius_meters' => 200,
        ]);
        $permit = $this->createPermit();
        $url = route('scanner.verify', ['token' => $permit->barcode_token]);

        $this->getJson($url)
            ->assertStatus(428)
            ->assertJsonPath('status', 'location_required');

        $this->getJson($url.'&'.http_build_query([
            'latitude' => -8.7212345,
            'longitude' => 115.1845678,
            'accuracy' => 15,
        ]))->assertOk()->assertJsonPath('valid', true);

        $this->getJson($url.'&'.http_build_query([
            'latitude' => -8.7112345,
            'longitude' => 115.1845678,
            'accuracy' => 15,
        ]))
            ->assertForbidden()
            ->assertJsonPath('status', 'location_denied')
            ->assertJsonMissing(['permit_number' => $permit->permit_number]);

        $this->getJson($url.'&'.http_build_query([
            'latitude' => -8.7212345,
            'longitude' => 115.1845678,
            'accuracy' => 250,
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('status', 'location_inaccurate');
    }

    public function test_browser_opening_an_existing_qr_link_is_redirected_to_the_public_scanner(): void
    {
        $permit = $this->createPermit();

        $this->get(route('scanner.verify', ['token' => $permit->barcode_token]))
            ->assertRedirect(route('scanner.index', ['token' => $permit->barcode_token]));
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
