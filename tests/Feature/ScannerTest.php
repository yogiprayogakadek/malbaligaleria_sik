<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureWebsiteOperating;
use App\Models\LoadingPermit;
use App\Models\ScannerIpBlock;
use App\Models\ScannerSetting;
use App\Models\ScannerVerificationLog;
use App\Models\SecurityGateEvent;
use App\Models\SecurityOfficer;
use App\Models\User;
use App\Models\WorkPermit;
use App\Notifications\ScannerRiskAlertPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureWebsiteOperating::class);
        Storage::fake('local');
    }

    public function test_scanner_page_requires_an_officer_shift_and_has_no_manual_token_field(): void
    {
        $this->get(route('scanner.index'))
            ->assertOk()
            ->assertSee('Mulai sesi shift')
            ->assertSee('scanner.webmanifest', false)
            ->assertSee('MBG Scanner')
            ->assertDontSee('Tempel token atau tautan QR')
            ->assertDontSee('type="file"', false)
            ->assertDontSee('cdn.jsdelivr.net');
    }

    public function test_scanner_has_a_separate_installable_pwa_identity(): void
    {
        $manifest = json_decode(file_get_contents(public_path('scanner.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
        $script = file_get_contents(resource_path('js/scanner.js'));

        $this->assertSame('/scanner', $manifest['id']);
        $this->assertSame('/scanner?source=pwa', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertStringContainsString("navigator.serviceWorker.register('/sw.js', { scope: '/' })", $script);
    }

    public function test_loading_evidence_uses_a_separate_full_camera_step(): void
    {
        $this->get(route('scanner.index'))
            ->assertOk()
            ->assertSee('id="evidenceCameraMode"', false)
            ->assertSee('id="confirmEvidenceButton"', false)
            ->assertSee('id="cancelEvidenceButton"', false)
            ->assertSee('Buka kamera');

        $script = file_get_contents(resource_path('js/scanner.js'));
        $this->assertStringContainsString("scannerShell.classList.add('evidence-camera-active')", $script);
        $this->assertStringContainsString("captureEvidenceButton?.addEventListener('click', enterEvidenceCamera)", $script);
        $this->assertStringContainsString('confirmEvidenceButton.disabled = true', $script);
        $this->assertStringContainsString('evidenceCaptureReadyAt = performance.now()', $script);
        $this->assertStringContainsString('if (!evidenceCameraActive || performance.now() < evidenceCaptureReadyAt) return', $script);
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
        $endDate = now()->addDays(2)->startOfDay();
        $permit = $this->createPermit([
            'end_date' => $endDate->toDateString(),
            'barcode_expires_at' => $endDate->copy()->addDay()->endOfDay(),
        ]);

        $this->getJson(route('scanner.verify', ['token' => $permit->barcode_token]))
            ->assertOk()
            ->assertJson([
                'valid' => true,
                'status' => 'active',
                'permit_number' => $permit->permit_number,
                'tenant_name' => $permit->tenant_name,
                'movement_time' => '22:30 WITA',
                'expires_at' => $endDate->format('d M Y').', 23:59 WITA',
            ])
            ->assertJsonStructure(['document_id', 'document_fingerprint']);
    }

    public function test_scanner_rejects_pending_and_expired_permits(): void
    {
        $pending = $this->createPermit(['status' => 'pending']);
        $expired = $this->createPermit([
            'end_date' => now()->subDay()->toDateString(),
            'barcode_expires_at' => now()->addDay(),
        ]);

        $this->getJson(route('scanner.verify', ['token' => $pending->barcode_token]))
            ->assertUnprocessable()
            ->assertJsonPath('status', 'not_approved');

        $this->getJson(route('scanner.verify', ['token' => $expired->barcode_token]))
            ->assertUnprocessable()
            ->assertJsonPath('status', 'expired');
    }

    public function test_generating_a_barcode_uses_the_end_of_the_permit_end_date(): void
    {
        $endDate = now()->addDays(4)->startOfDay();
        $permit = $this->createPermit([
            'end_date' => $endDate->toDateString(),
            'barcode_token' => null,
            'barcode_expires_at' => null,
        ]);

        $permit->generateBarcodeToken();

        $this->assertSame(
            $endDate->copy()->endOfDay()->format('Y-m-d H:i:s'),
            $permit->fresh()->barcode_expires_at?->format('Y-m-d H:i:s'),
        );
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

    public function test_scanner_records_audit_without_storing_the_raw_token(): void
    {
        $permit = $this->createPermit();

        $this->getJson(route('scanner.verify', ['token' => $permit->barcode_token]))->assertOk();

        $log = ScannerVerificationLog::sole();
        $this->assertSame(hash('sha256', $permit->barcode_token), $log->token_hash);
        $this->assertNotSame($permit->barcode_token, $log->token_hash);
        $this->assertSame('loading', $log->permit_type);
        $this->assertSame($permit->id, $log->permit_id);
        $this->assertSame('active', $log->result);
        $this->assertSame(hash_hmac('sha256', '127.0.0.1', (string) config('app.key')), $log->ip_hash);
    }

    public function test_only_admin_can_open_scanner_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000010']);
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081200000011']);

        $this->get(route('admin.scanner-logs.index'))->assertRedirect(route('login'));
        $this->actingAs($tenant)->get(route('admin.scanner-logs.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.scanner-logs.index'))->assertOk()->assertSee('Riwayat verifikasi QR');
    }

    public function test_admin_scanner_log_highlights_repeated_risky_sources(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000012']);

        ScannerVerificationLog::withoutEvents(function (): void {
            foreach (range(1, 5) as $index) {
                ScannerVerificationLog::create([
                    'token_hash' => hash('sha256', 'invalid-'.$index),
                    'result' => 'invalid',
                    'status_code' => 422,
                    'ip_address' => '192.168.0.99',
                    'ip_hash' => hash_hmac('sha256', '192.168.0.99', (string) config('app.key')),
                    'user_agent' => 'Test browser',
                ]);
            }
        });

        $this->actingAs($admin)
            ->get(route('admin.scanner-logs.index'))
            ->assertOk()
            ->assertSee('Aktivitas yang perlu diperiksa')
            ->assertSee('192.168.0.99')
            ->assertSee('5 kejadian');
    }

    public function test_repeated_risky_scans_are_blocked_and_alert_admin(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000013']);
        ScannerSetting::query()->findOrFail(1)->update([
            'risk_monitoring_enabled' => true,
            'auto_block_enabled' => true,
            'risk_threshold' => 3,
            'risk_window_minutes' => 15,
            'block_duration_minutes' => 60,
            'risk_alerts_enabled' => true,
        ]);

        foreach (range(1, 3) as $attempt) {
            $this->getJson(route('scanner.verify', ['token' => 'invalid-'.$attempt]))->assertBadRequest();
        }

        $block = ScannerIpBlock::sole();
        $this->assertTrue($block->isActive());
        $this->assertSame(3, $block->trigger_count);
        $this->getJson(route('scanner.verify', ['token' => str_repeat('A', 64)]))
            ->assertStatus(429)
            ->assertJsonPath('status', 'blocked');
        $this->assertDatabaseHas('permit_notifications', ['user_id' => $admin->id, 'type' => 'scanner_risk']);
        Notification::assertSentTo($admin, ScannerRiskAlertPush::class);
    }

    public function test_admin_can_unblock_scanner_source_with_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000014']);
        $block = ScannerIpBlock::create([
            'ip_hash' => hash('sha256', 'source'),
            'ip_address' => '192.168.1.20',
            'trigger_count' => 5,
            'last_result' => 'invalid',
            'blocked_until' => now()->addHour(),
        ]);

        $this->actingAs($admin)->delete(route('admin.scanner-blocks.destroy', $block), [
            'current_password' => 'password',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertFalse($block->fresh()->isActive());
        $this->assertSame($admin->id, $block->fresh()->unblocked_by);
    }

    public function test_scanner_denies_a_tampered_official_document_and_records_the_failure(): void
    {
        $permit = $this->createPermit();
        $url = route('scanner.verify', ['token' => $permit->barcode_token]);

        $this->getJson($url)->assertOk();
        $permit->refresh();
        Storage::disk('local')->put($permit->official_document_path, 'tampered document');

        $this->getJson($url)
            ->assertStatus(503)
            ->assertJsonPath('status', 'integrity_error');

        $this->assertSame('integrity_error', ScannerVerificationLog::latest('id')->value('result'));
    }

    public function test_loading_gate_event_requires_officer_challenge_and_camera_evidence(): void
    {
        $permit = $this->createPermit();
        $officer = SecurityOfficer::query()->create([
            'name' => 'Petugas Uji',
            'officer_id' => 'SEC-TEST',
            'whatsapp' => '628123450000',
            'pin_hash' => Hash::make('123456'),
            'is_active' => true,
        ]);

        $this->postJson(route('scanner.gate-events.store'), [
            'token' => $permit->barcode_token,
            'event' => 'check_in',
        ])->assertUnprocessable();

        $this->post(route('scanner.officer-session.store'), ['officer_id' => $officer->officer_id, 'pin' => '123456'])
            ->assertRedirect(route('scanner.index'));
        $verification = $this->getJson(route('scanner.verify', ['token' => $permit->barcode_token]))
            ->assertOk()
            ->json();

        $this->post(route('scanner.gate-events.store'), [
            'token' => $permit->barcode_token,
            'event' => 'check_in',
            'challenge' => $verification['gate_challenge'],
            'evidence_photo' => UploadedFile::fake()->image('loading.jpg', 900, 1200),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('gate_status', 'check_in');

        $this->assertDatabaseCount('security_gate_events', 1);
        $this->assertDatabaseHas('security_gate_events', ['security_officer_id' => $officer->id]);
        $this->assertDatabaseMissing('security_gate_events', ['token_hash' => $permit->barcode_token]);
        $event = SecurityGateEvent::sole();
        Storage::disk('local')->assertExists($event->evidence_path);
    }

    public function test_inactive_officer_session_cannot_receive_a_gate_challenge(): void
    {
        $permit = $this->createPermit();
        $officer = SecurityOfficer::query()->create([
            'name' => 'Petugas Berganti',
            'officer_id' => 'SEC-ROLLING',
            'whatsapp' => '628123450001',
            'pin_hash' => Hash::make('654321'),
            'is_active' => true,
        ]);
        $this->post(route('scanner.officer-session.store'), ['officer_id' => $officer->officer_id, 'pin' => '654321']);
        $officer->update(['is_active' => false]);

        $this->getJson(route('scanner.verify', ['token' => $permit->barcode_token]))
            ->assertOk()
            ->assertJsonPath('gate_challenge', null);
    }

    public function test_only_admin_can_open_an_untampered_gate_evidence_photo(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000093']);
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081200000094']);
        $permit = $this->createPermit();
        $path = 'scanner/evidence/2026/10/test.jpg';
        $content = 'private-photo-content';
        Storage::disk('local')->put($path, $content);
        $event = SecurityGateEvent::query()->create([
            'permit_type' => 'loading',
            'permit_id' => $permit->id,
            'event' => 'check_in',
            'token_hash' => hash('sha256', $permit->barcode_token),
            'evidence_path' => $path,
            'evidence_sha256' => hash('sha256', $content),
            'occurred_at' => now(),
        ]);

        $this->actingAs($tenant)->get(route('admin.scanner-gate-events.evidence', $event))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.scanner-gate-events.evidence', $event))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_admin_and_tr_can_review_loading_evidence_but_other_accounts_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000095']);
        $tr = User::factory()->create(['role' => 'validator', 'division' => 'TR', 'phone' => '081200000096']);
        $mep = User::factory()->create(['role' => 'validator', 'division' => 'MEP', 'phone' => '081200000097']);
        $permit = $this->createPermit();
        $officer = SecurityOfficer::query()->create([
            'name' => 'Petugas Bukti',
            'officer_id' => 'SEC-BUKTI',
            'whatsapp' => '628123450009',
            'pin_hash' => Hash::make('123456'),
            'is_active' => true,
        ]);
        $path = 'scanner/evidence/2026/10/review.jpg';
        $content = 'untampered-private-photo';
        Storage::disk('local')->put($path, $content);
        $event = SecurityGateEvent::query()->create([
            'permit_type' => 'loading',
            'permit_id' => $permit->id,
            'event' => 'check_in',
            'security_officer_id' => $officer->id,
            'token_hash' => hash('sha256', $permit->barcode_token),
            'evidence_path' => $path,
            'evidence_sha256' => hash('sha256', $content),
            'occurred_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.loading-evidence.index'))
            ->assertOk()
            ->assertSee($permit->permit_number)
            ->assertSee('Petugas Bukti')
            ->assertSee('SEC-BUKTI');
        $this->actingAs($tr)->get(route('tr.loading-evidence.index'))
            ->assertOk()
            ->assertSee($permit->permit_number)
            ->assertSee('Bukti akses loading');
        $this->actingAs($tr)->get(route('tr.loading-evidence.evidence', $event))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
        Storage::disk('local')->put($path, 'photo-has-been-modified');
        $this->actingAs($tr)->get(route('tr.loading-evidence.evidence', $event))->assertStatus(409);
        $this->actingAs($mep)->get(route('tr.loading-evidence.index'))->assertForbidden();
        $this->actingAs($tr)->get(route('admin.loading-evidence.index'))->assertForbidden();
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
