<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureWebsiteOperating;
use App\Jobs\SendWhatsappMessage;
use App\Models\AuditLog;
use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\ScannerIpBlock;
use App\Models\ScannerSetting;
use App\Models\ScannerVerificationLog;
use App\Models\SecurityGateEvent;
use App\Models\SecurityOfficer;
use App\Models\User;
use App\Models\WhatsappMessageLog;
use App\Models\WhatsappSetting;
use App\Models\WorkPermit;
use App\Notifications\ScannerRiskAlertPush;
use App\Services\OfficialLetterCodeService;
use App\Services\PermitPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
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
            ->assertSee('type="file"', false)
            ->assertDontSee('cdn.jsdelivr.net');
    }

    public function test_scanner_has_a_separate_installable_pwa_identity(): void
    {
        $manifest = json_decode(file_get_contents(public_path('scanner.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
        $script = file_get_contents(resource_path('js/scanner.js'));
        $this->assertStringContainsString("addField('Status Surat', 'Selesai — seluruh barang keluar telah tercatat.', 'invalid')", $script);

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
            ->assertSee('Tambahkan Bukti')
            ->assertSee('Bukti Masuk')
            ->assertSee('Bukti Keluar')
            ->assertSee('Jumlah barang keluar')
            ->assertSee('Catatan security untuk TR')
            ->assertSee('id="downloadOfficialLetterLink"', false)
            ->assertSee('Unduh Surat Izin');

        $script = file_get_contents(resource_path('js/scanner.js'));
        $this->assertStringContainsString("scannerShell.classList.add('evidence-camera-active')", $script);
        $this->assertStringContainsString("captureEvidenceButton?.addEventListener('click', event =>", $script);
        $this->assertStringContainsString('confirmEvidenceButton.disabled = true', $script);
        $this->assertStringContainsString('evidenceCaptureReadyAt = performance.now()', $script);
        $this->assertStringContainsString('if (!evidenceCameraActive || performance.now() < evidenceCaptureReadyAt) return', $script);
        $this->assertStringContainsString('scannerDock.hidden = true', $script);
        $this->assertStringContainsString("confirmEvidenceButton.addEventListener('pointerdown', armEvidenceShutter)", $script);
        $this->assertStringContainsString("confirmEvidenceButton.addEventListener('pointerup', releaseEvidenceShutter)", $script);
        $this->assertStringContainsString("confirmEvidenceButton.addEventListener('touchstart', armEvidenceShutter", $script);
        $this->assertStringContainsString('if (!evidenceShutterGesture) return', $script);
        $this->assertStringContainsString('configureOfficialLetterDownload(data)', $script);
        $this->assertStringContainsString('resetOfficialLetterDownload()', $script);
        $this->assertStringNotContainsString("addEventListener('click', captureEvidence)", $script);
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

    public function test_active_officer_can_download_a_verified_loading_letter(): void
    {
        $permit = $this->createPermit();
        $officer = $this->startOfficerSession();

        $verification = $this->getJson(route('scanner.verify', ['token' => $permit->barcode_token]))
            ->assertOk()
            ->assertJsonStructure(['official_letter_download_url'])
            ->json();

        $download = $this->get($verification['official_letter_download_url'])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('no-store', (string) $download->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $download->headers->get('Cache-Control'));

        $audit = AuditLog::query()->where('event', 'scanner_letter_downloaded')->sole();
        $this->assertSame($permit->id, $audit->auditable_id);
        $this->assertSame('loading', $audit->new_values['permit_type']);
        $this->assertSame($officer->id, $audit->new_values['security_officer_id']);
        $this->assertArrayNotHasKey('storage_path', $audit->new_values);
    }

    public function test_scanner_download_url_requires_an_active_officer_and_a_valid_signature(): void
    {
        $permit = $this->createPermit();

        $this->getJson(route('scanner.verify', ['token' => $permit->barcode_token]))
            ->assertOk()
            ->assertJsonMissingPath('official_letter_download_url');

        $signedUrl = URL::temporarySignedRoute(
            'scanner.permits.loading.letter',
            now()->addMinutes(5),
            ['permit' => $permit->id],
        );
        $this->get($signedUrl)->assertUnauthorized();

        $this->startOfficerSession();
        $this->get(route('scanner.permits.loading.letter', $permit))->assertForbidden();

        $this->travel(6)->minutes();
        $this->get($signedUrl)->assertForbidden();
    }

    public function test_scanner_download_rechecks_permit_state_and_document_integrity(): void
    {
        $permit = $this->createPermit();
        $this->startOfficerSession();
        $downloadUrl = $this->getJson(route('scanner.verify', ['token' => $permit->barcode_token]))
            ->assertOk()
            ->json('official_letter_download_url');

        $permit->update(['revoked_at' => now()]);
        $this->get($downloadUrl)->assertStatus(410);

        $validPermit = $this->createPermit();
        $validDownloadUrl = $this->getJson(route('scanner.verify', ['token' => $validPermit->barcode_token]))
            ->assertOk()
            ->json('official_letter_download_url');
        $sealed = $validPermit->fresh();
        Storage::disk('local')->put($sealed->official_document_path, 'modified-official-letter');

        $this->get($validDownloadUrl)->assertStatus(409);
    }

    public function test_active_officer_can_download_verified_work_and_event_letters(): void
    {
        $this->startOfficerSession();

        foreach ([
            $this->createWorkPermit(),
            $this->createWorkPermit(['permit_kind' => 'event', 'work_category' => 'kol_event', 'assigned_division' => 'EP']),
        ] as $permit) {
            $verification = $this->getJson(route('scanner.verify', ['token' => $permit->public_token]))
                ->assertOk()
                ->assertJsonStructure(['official_letter_download_url'])
                ->json();

            $this->get($verification['official_letter_download_url'])
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_completed_loading_letter_remains_downloadable_without_new_evidence_action(): void
    {
        $permit = $this->createPermit(['direction' => 'out', 'item_count' => 1]);
        $this->startOfficerSession();
        $this->recordGateEvent($permit, 'check_out', 1);

        $verification = $this->getJson(route('scanner.verify', ['token' => $permit->barcode_token]))
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('gate_challenge', null)
            ->assertJsonStructure(['official_letter_download_url'])
            ->json();

        $this->get($verification['official_letter_download_url'])->assertOk();
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
        $reviewer = User::factory()->create([
            'role' => 'validator',
            'division' => 'TR',
            'phone' => '081200000098',
            'whatsapp_notifications_enabled' => true,
        ]);
        $otherValidator = User::factory()->create([
            'role' => 'validator',
            'division' => 'TR',
            'phone' => '081200000099',
            'whatsapp_notifications_enabled' => true,
        ]);
        WhatsappSetting::query()->create([
            'base_url' => 'http://192.168.0.253:3000',
            'api_key' => 'waha-private-api-key-123456',
            'session' => 'default',
            'timeout' => 10,
            'is_active' => true,
            'notify_applicants' => true,
        ]);
        Queue::fake();
        $permit = $this->createPermit(['reviewed_by' => $reviewer->id]);
        $officer = SecurityOfficer::query()->create([
            'name' => 'Petugas Uji',
            'officer_id' => 'SEC-TEST',
            'whatsapp' => '628123450000',
            'pin_hash' => Hash::make('123456'),
            'is_active' => true,
        ]);

        $this->postJson(route('scanner.gate-events.store'), [
            'token' => $permit->barcode_token,
            'event' => 'check_out',
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

        $notification = PermitNotification::query()->sole();
        $this->assertSame($reviewer->id, $notification->user_id);
        $this->assertSame('gate_evidence_uploaded', $notification->type);
        $this->assertSame('Foto Bukti Barang Masuk', $notification->title);
        $this->assertStringContainsString($permit->permit_number, $notification->body);
        $this->assertFalse(PermitNotification::query()->where('user_id', $otherValidator->id)->exists());

        $this->actingAs($reviewer)
            ->post(route('tr.notifications.read', $notification))
            ->assertRedirect(route('tr.loading-evidence.index', ['q' => $permit->permit_number]));
        $this->assertNotNull($notification->fresh()->read_at);

        $whatsapp = WhatsappMessageLog::query()->sole();
        $this->assertSame($reviewer->id, $whatsapp->user_id);
        $this->assertSame('gate_evidence_uploaded_'.$event->id, $whatsapp->event);
        $this->assertStringContainsString('Foto Bukti Barang Masuk', $whatsapp->message);
        $this->assertStringContainsString($permit->permit_number, $whatsapp->message);
        $this->assertStringContainsString('Petugas Uji', $whatsapp->message);
        $this->assertStringContainsString(route('tr.loading-evidence.index', ['q' => $permit->permit_number]), $whatsapp->message);
        $this->assertDatabaseMissing('whatsapp_message_logs', ['user_id' => $otherValidator->id]);
        Queue::assertPushed(SendWhatsappMessage::class, 1);
    }

    public function test_outbound_quantity_can_be_recorded_partially_until_the_permit_is_completed(): void
    {
        $permit = $this->createPermit(['direction' => 'out', 'item_count' => 10]);
        $officer = $this->startOfficerSession();

        $firstCheckout = $this->recordGateEvent($permit, 'check_out', 5, 'Lima barang keluar');
        $firstCheckout->assertJsonPath('outbound_progress.recorded', 5)
            ->assertJsonPath('outbound_progress.remaining', 5)
            ->assertJsonPath('outbound_progress.completed', false);

        $partial = $this->getJson(route('scanner.verify', ['token' => $permit->barcode_token]))
            ->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('outbound_progress.remaining', 5)
            ->assertJsonCount(1, 'gate_history')
            ->json();
        $this->assertNotNull($partial['gate_challenge']);
        $this->assertNotNull($partial['gate_history'][0]['evidence_url']);

        $secondCheckout = $this->recordGateEvent($permit, 'check_out', 5, 'Seluruh barang sudah keluar');
        $secondCheckout->assertJsonPath('outbound_progress.recorded', 10)
            ->assertJsonPath('outbound_progress.remaining', 0)
            ->assertJsonPath('outbound_progress.completed', true);

        $this->getJson(route('scanner.verify', ['token' => $permit->barcode_token]))
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('gate_challenge', null)
            ->assertJsonCount(2, 'gate_history');

        $this->assertSame(10, SecurityGateEvent::query()->where('event', 'check_out')->sum('item_quantity'));
        $this->assertSame('Seluruh barang sudah keluar', SecurityGateEvent::query()->latest('id')->value('security_note'));
        $this->assertSame($officer->id, SecurityGateEvent::query()->latest('id')->value('security_officer_id'));
    }

    public function test_outbound_quantity_cannot_exceed_the_remaining_items(): void
    {
        $permit = $this->createPermit(['direction' => 'both', 'item_count' => 10]);
        $this->startOfficerSession();

        $verification = $this->getJson(route('scanner.verify', ['token' => $permit->barcode_token]))->json();
        $filesBefore = Storage::disk('local')->allFiles('scanner/evidence');
        $this->post(route('scanner.gate-events.store'), [
            'token' => $permit->barcode_token,
            'event' => 'check_out',
            'challenge' => $verification['gate_challenge'],
            'item_quantity' => 11,
            'evidence_photo' => UploadedFile::fake()->image('too-many.jpg', 900, 1200),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('item_quantity');

        $this->assertDatabaseCount('security_gate_events', 0);
        $this->assertSame($filesBefore, Storage::disk('local')->allFiles('scanner/evidence'));
    }

    public function test_outbound_checkout_requires_a_positive_whole_quantity(): void
    {
        $permit = $this->createPermit(['direction' => 'out', 'item_count' => 10]);
        $this->startOfficerSession();

        $verification = $this->getJson(route('scanner.verify', ['token' => $permit->barcode_token]))->json();
        $this->post(route('scanner.gate-events.store'), [
            'token' => $permit->barcode_token,
            'event' => 'check_out',
            'challenge' => $verification['gate_challenge'],
            'evidence_photo' => UploadedFile::fake()->image('missing-quantity.jpg', 900, 1200),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('item_quantity');

        foreach ([0, -1, '1.5'] as $invalidQuantity) {
            $verification = $this->getJson(route('scanner.verify', ['token' => $permit->barcode_token]))->json();
            $this->post(route('scanner.gate-events.store'), [
                'token' => $permit->barcode_token,
                'event' => 'check_out',
                'challenge' => $verification['gate_challenge'],
                'item_quantity' => $invalidQuantity,
                'evidence_photo' => UploadedFile::fake()->image('invalid-quantity.jpg', 900, 1200),
            ], ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('item_quantity');
        }

        $this->assertDatabaseCount('security_gate_events', 0);
    }

    public function test_loading_direction_controls_allowed_evidence_and_both_allows_free_order(): void
    {
        $this->startOfficerSession();
        $incoming = $this->createPermit(['direction' => 'in']);
        $outgoing = $this->createPermit(['direction' => 'out', 'item_count' => 5]);
        $both = $this->createPermit(['direction' => 'both', 'item_count' => 5]);

        $incomingVerification = $this->getJson(route('scanner.verify', ['token' => $incoming->barcode_token]))->json();
        $this->post(route('scanner.gate-events.store'), [
            'token' => $incoming->barcode_token,
            'event' => 'check_out',
            'item_quantity' => 1,
            'challenge' => $incomingVerification['gate_challenge'],
            'evidence_photo' => UploadedFile::fake()->image('wrong-out.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(409);

        $outgoingVerification = $this->getJson(route('scanner.verify', ['token' => $outgoing->barcode_token]))->json();
        $this->post(route('scanner.gate-events.store'), [
            'token' => $outgoing->barcode_token,
            'event' => 'check_in',
            'challenge' => $outgoingVerification['gate_challenge'],
            'evidence_photo' => UploadedFile::fake()->image('wrong-in.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(409);

        $this->recordGateEvent($both, 'check_out', 2);
        $this->recordGateEvent($both, 'check_out', 1);
        $this->recordGateEvent($both, 'check_in');

        $this->assertSame(['check_out', 'check_out', 'check_in'], $both->gateEvents()->oldest('id')->pluck('event')->all());
    }

    public function test_letter_code_requires_an_officer_and_can_record_evidence_without_exposing_the_qr_token(): void
    {
        $permit = $this->createPermit(['direction' => 'out', 'item_count' => 3]);
        app(PermitPdfService::class)->sealLoadingPermit($permit);
        $permit->refresh();

        $this->getJson(route('scanner.verify', ['letter_code' => $permit->formatted_letter_code]))
            ->assertUnauthorized()
            ->assertJsonPath('status', 'officer_required');

        $this->startOfficerSession();
        $this->get(route('scanner.index'))
            ->assertOk()
            ->assertSee('Masukkan Kode Surat')
            ->assertSee('Kode Surat');
        $verification = $this->getJson(route('scanner.verify', ['letter_code' => strtolower($permit->formatted_letter_code)]))
            ->assertOk()
            ->assertJsonPath('direction_code', 'out')
            ->assertJsonPath('item_description', 'Barang pengujian')
            ->assertJsonPath('item_unit', 'koli')
            ->assertJsonMissing(['token' => $permit->barcode_token])
            ->json();

        $this->post(route('scanner.gate-events.store'), [
            'letter_code' => strtolower($permit->formatted_letter_code),
            'event' => 'check_out',
            'item_quantity' => 3,
            'challenge' => $verification['gate_challenge'],
            'evidence_photo' => UploadedFile::fake()->image('manual-code.jpg', 900, 1200),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('outbound_progress.completed', true);

        $this->assertDatabaseHas('security_gate_events', [
            'permit_id' => $permit->id,
            'event' => 'check_out',
            'item_quantity' => 3,
            'token_hash' => hash('sha256', $permit->barcode_token),
        ]);
    }

    public function test_letter_code_verifies_work_and_event_permits_for_an_active_officer(): void
    {
        $this->startOfficerSession();

        foreach ([
            'work' => $this->createWorkPermit(),
            'event' => $this->createWorkPermit([
                'permit_kind' => 'event',
                'work_category' => 'kol_event',
                'assigned_division' => 'EP',
            ]),
        ] as $type => $permit) {
            app(PermitPdfService::class)->sealWorkPermit($permit);
            $permit->refresh();

            $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $permit->letter_code);
            $this->getJson(route('scanner.verify', ['letter_code' => strtolower($permit->formatted_letter_code)]))
                ->assertOk()
                ->assertJsonPath('valid', true)
                ->assertJsonPath('permit_type', $type)
                ->assertJsonPath('permit_type_label', $type === 'event' ? 'Surat Izin Event' : 'Surat Izin Kerja')
                ->assertJsonPath('permit_number', $permit->permit_number)
                ->assertJsonStructure(['official_letter_download_url'])
                ->assertJsonMissing(['token' => $permit->official_scan_token])
                ->assertJsonMissing(['token' => $permit->public_token]);
        }
    }

    public function test_replaced_letter_code_is_rejected(): void
    {
        $permit = $this->createWorkPermit();
        app(PermitPdfService::class)->sealWorkPermit($permit);
        $permit->refresh();
        $oldCode = $permit->formatted_letter_code;
        app(OfficialLetterCodeService::class)->rotate($permit, (string) Str::uuid());
        $this->startOfficerSession();

        $this->getJson(route('scanner.verify', ['letter_code' => $oldCode]))
            ->assertStatus(410)
            ->assertJsonPath('status', 'replaced')
            ->assertJsonPath('permit_number', $permit->permit_number);
    }

    public function test_public_verification_does_not_expose_security_history_or_notes(): void
    {
        $permit = $this->createPermit();
        SecurityGateEvent::query()->create([
            'permit_type' => 'loading',
            'permit_id' => $permit->id,
            'event' => 'check_in',
            'security_note' => 'Catatan internal TR',
            'token_hash' => hash('sha256', $permit->barcode_token),
            'occurred_at' => now(),
        ]);

        $this->getJson(route('scanner.verify', ['token' => $permit->barcode_token]))
            ->assertOk()
            ->assertJsonPath('gate_history', [])
            ->assertJsonMissing(['security_note' => 'Catatan internal TR']);
    }

    public function test_legacy_checkout_without_quantity_completes_an_outbound_permit(): void
    {
        $permit = $this->createPermit(['direction' => 'out', 'item_count' => 10]);
        SecurityGateEvent::query()->create([
            'permit_type' => 'loading',
            'permit_id' => $permit->id,
            'event' => 'check_out',
            'token_hash' => hash('sha256', $permit->barcode_token),
            'occurred_at' => now(),
        ]);

        $this->getJson(route('scanner.verify', ['token' => $permit->barcode_token]))
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('outbound_progress.legacy_completed', true)
            ->assertJsonPath('outbound_progress.remaining', 0)
            ->assertJsonPath('gate_challenge', null);
    }

    public function test_scanner_evidence_url_requires_a_valid_signature_and_active_officer_session(): void
    {
        $permit = $this->createPermit();
        $path = 'scanner/evidence/2026/11/security.jpg';
        $content = 'private-security-photo';
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
        $url = URL::temporarySignedRoute('scanner.gate-events.evidence', now()->addMinute(), [
            'securityGateEvent' => $event,
            'permit' => $permit->id,
        ]);

        $this->get($url)->assertUnauthorized();
        $this->startOfficerSession();
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $wrongPermitUrl = URL::temporarySignedRoute('scanner.gate-events.evidence', now()->addMinute(), [
            'securityGateEvent' => $event,
            'permit' => $permit->id + 1,
        ]);
        $this->get($wrongPermitUrl)->assertNotFound();
        $this->get($url.'&signature=changed')->assertForbidden();
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
        $permit = $this->createPermit(['direction' => 'out', 'item_count' => 5]);
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
            'event' => 'check_out',
            'item_quantity' => 3,
            'security_note' => 'Periksa kemasan sisi kiri',
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
            ->assertSee('SEC-BUKTI')
            ->assertSee('3 koli')
            ->assertSee('Periksa kemasan sisi kiri');
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

    private function createWorkPermit(array $attributes = []): WorkPermit
    {
        return WorkPermit::create(array_merge([
            'permit_kind' => 'work',
            'public_token' => Str::random(64),
            'contractor_name' => 'Kontraktor Pengujian',
            'applicant_name' => 'Penanggung Jawab',
            'applicant_phone' => '081234567890',
            'work_location' => 'Unit GF-12',
            'work_category' => 'other',
            'event_type' => 'organizer',
            'event_requirements' => [],
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
        ], $attributes));
    }

    private function startOfficerSession(): SecurityOfficer
    {
        $officer = SecurityOfficer::query()->create([
            'name' => 'Petugas Progres',
            'officer_id' => 'SEC-'.Str::upper(Str::random(8)),
            'whatsapp' => '628'.random_int(1000000000, 9999999999),
            'pin_hash' => Hash::make('123456'),
            'is_active' => true,
        ]);
        $this->post(route('scanner.officer-session.store'), [
            'officer_id' => $officer->officer_id,
            'pin' => '123456',
        ])->assertRedirect(route('scanner.index'));

        return $officer;
    }

    private function recordGateEvent(
        LoadingPermit $permit,
        string $event,
        ?int $quantity = null,
        ?string $note = null,
    ): TestResponse {
        $verification = $this->getJson(route('scanner.verify', ['token' => $permit->barcode_token]))
            ->assertOk()
            ->json();
        $payload = [
            'token' => $permit->barcode_token,
            'event' => $event,
            'challenge' => $verification['gate_challenge'],
            'evidence_photo' => UploadedFile::fake()->image($event.'.jpg', 900, 1200),
        ];
        if ($quantity !== null) {
            $payload['item_quantity'] = $quantity;
        }
        if ($note !== null) {
            $payload['security_note'] = $note;
        }

        return $this->post(route('scanner.gate-events.store'), $payload, ['Accept' => 'application/json'])
            ->assertOk();
    }
}
