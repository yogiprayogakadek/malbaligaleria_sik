<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EmailMessageLog;
use App\Models\LoadingPermit;
use App\Models\PermitNumberSetting;
use App\Models\SecuritySetting;
use App\Models\User;
use App\Services\AdminDataMaintenanceService;
use App\Services\AuditIntegrityService;
use App\Services\EncryptedBackupManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class AdminDataMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_data_maintenance_and_number_settings(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081200009001']);
        $admin = $this->admin();

        $this->actingAs($tenant)->get(route('admin.data-maintenance.index'))->assertForbidden();
        $this->actingAs($tenant)->get(route('admin.settings.permit-numbers.edit'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.data-maintenance.index'))->assertOk()->assertSee('Penghapusan data operasional');
        $this->actingAs($admin)->get(route('admin.settings.permit-numbers.edit'))->assertOk()->assertSee('Format nomor surat');
    }

    public function test_admin_can_change_number_pattern_with_restricted_placeholders(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.settings.permit-numbers.update'), [
            'permit_type' => 'loading',
            'pattern' => 'MBG/LOG/{year_short}/{month}/{sequence}',
            'sequence_padding' => 5,
            'reset_period' => 'yearly',
            'current_password' => 'password',
        ])->assertRedirect();

        $this->assertSame('MBG/LOG/{year_short}/{month}/{sequence}', PermitNumberSetting::for('loading')->pattern);
        $this->assertMatchesRegularExpression('/^MBG\/LOG\/\d{2}\/\d{2}\/\d{5}$/', LoadingPermit::generatePermitNumber());

        $this->actingAs($admin)->from(route('admin.settings.permit-numbers.edit'))->put(route('admin.settings.permit-numbers.update'), [
            'permit_type' => 'loading',
            'pattern' => 'MBG/{tenant}/{sequence}',
            'sequence_padding' => 4,
            'reset_period' => 'global',
            'current_password' => 'password',
        ])->assertRedirect(route('admin.settings.permit-numbers.edit'))->assertSessionHasErrors('pattern');
    }

    public function test_admin_can_delete_selected_permit_and_its_private_document(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $deleted = $this->permit('MBG/SIK/X/0901', 'permits/id-docs/delete.jpg');
        $kept = $this->permit('MBG/SIK/X/0902', 'permits/id-docs/keep.jpg');
        Storage::disk('local')->put($deleted->id_doc_path, 'private');
        Storage::disk('local')->put($kept->id_doc_path, 'private');

        $this->actingAs($admin)->delete(route('admin.data-maintenance.destroy'), [
            'dataset' => 'loading',
            'delete_mode' => 'selected',
            'record_ids' => [$deleted->id],
            'reason' => 'Menghapus data pengujian yang tidak digunakan.',
            'current_password' => 'password',
        ])->assertRedirect(route('admin.data-maintenance.index', ['dataset' => 'loading']));

        $this->assertDatabaseMissing('loading_permits', ['id' => $deleted->id]);
        $this->assertDatabaseHas('loading_permits', ['id' => $kept->id]);
        Storage::disk('local')->assertMissing($deleted->id_doc_path);
        Storage::disk('local')->assertExists($kept->id_doc_path);
        $this->assertTrue(AuditLog::query()->where('event', 'data_deleted')->exists());
    }

    public function test_delete_all_requires_exact_confirmation_phrase(): void
    {
        $admin = $this->admin();
        $this->permit('MBG/SIK/X/0911', 'permits/id-docs/one.jpg');
        $this->permit('MBG/SIK/X/0912', 'permits/id-docs/two.jpg');

        $payload = [
            'dataset' => 'loading',
            'delete_mode' => 'all',
            'confirmation' => 'hapus semua',
            'reason' => 'Membersihkan seluruh data pengujian lama.',
            'current_password' => 'password',
        ];
        $this->actingAs($admin)->delete(route('admin.data-maintenance.destroy'), $payload)
            ->assertSessionHasErrors('confirmation');
        $this->assertSame(2, LoadingPermit::query()->count());

        $payload['confirmation'] = 'HAPUS SEMUA';
        $this->actingAs($admin)->delete(route('admin.data-maintenance.destroy'), $payload)->assertRedirect();
        $this->assertSame(0, LoadingPermit::query()->count());
    }

    public function test_active_delivery_logs_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $queued = EmailMessageLog::query()->create([
            'recipient' => 'tenant@example.test',
            'recipient_hash' => hash('sha256', 'tenant@example.test'),
            'notification_type' => 'TestNotification',
            'subject' => 'Pesan mengantre',
            'status' => 'retrying',
            'attempts' => 1,
        ]);

        $this->actingAs($admin)->delete(route('admin.data-maintenance.destroy'), [
            'dataset' => 'email_logs',
            'delete_mode' => 'selected',
            'record_ids' => [$queued->id],
            'reason' => 'Menguji perlindungan log pengiriman aktif.',
            'current_password' => 'password',
        ])->assertRedirect();

        $this->assertDatabaseHas('email_message_logs', ['id' => $queued->id, 'status' => 'retrying']);
    }

    public function test_production_reset_route_is_hidden_when_flag_is_disabled(): void
    {
        $admin = $this->admin();
        SecuritySetting::query()->findOrFail(1)->update(['sensitive_actions_require_2fa' => true]);

        $this->actingAs($admin)->get(route('admin.data-maintenance.index'))
            ->assertRedirect(route('admin.sensitive-confirm.show'));

        SecuritySetting::query()->findOrFail(1)->update(['sensitive_actions_require_2fa' => false]);
        $this->actingAs($admin)->get(route('admin.data-maintenance.index'))
            ->assertOk()
            ->assertDontSee('Reset data untuk go-live');

        SecuritySetting::query()->findOrFail(1)->update(['sensitive_actions_require_2fa' => true]);

        $this->actingAs($admin)->delete(route('admin.data-maintenance.production-reset'), [
            'confirmation' => 'RESET PRODUKSI',
            'reason' => 'Menyiapkan database untuk layanan produksi.',
            'current_password' => 'password',
        ])->assertNotFound();
    }

    public function test_only_admin_can_access_enabled_production_reset(): void
    {
        $this->enableProductionReset();
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081200009105']);

        $this->actingAs($tenant)->delete(route('admin.data-maintenance.production-reset'), [
            'confirmation' => 'RESET PRODUKSI',
            'reason' => 'Menyiapkan database untuk layanan produksi.',
            'current_password' => 'password',
        ])->assertForbidden();

        $this->assertDatabaseMissing('audit_logs', ['event' => 'production_data_reset']);
    }

    public function test_enabled_production_reset_shows_ready_form(): void
    {
        $this->enableProductionReset();

        $this->actingAs($this->admin())->get(route('admin.data-maintenance.index'))
            ->assertOk()
            ->assertSee('Reset data untuk go-live')
            ->assertSee('Buat backup dan reset data')
            ->assertSee('Membuat backup dan mereset data', false);
    }

    public function test_production_reset_requires_exact_phrase_before_backup_runs(): void
    {
        $this->enableProductionReset();
        $admin = $this->admin();
        $permit = $this->permit('MBG/SIK/X/0921', 'permits/id-docs/phrase.jpg');
        $this->mock(EncryptedBackupManager::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('create');
        });

        $this->actingAs($admin)->from(route('admin.data-maintenance.index'))->delete(route('admin.data-maintenance.production-reset'), [
            'confirmation' => 'reset produksi',
            'reason' => 'Menyiapkan database untuk layanan produksi.',
            'current_password' => 'password',
        ])->assertRedirect(route('admin.data-maintenance.index'))->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('loading_permits', ['id' => $permit->id]);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'production_data_reset']);
    }

    public function test_production_reset_allows_input_corrections_before_throttling(): void
    {
        $this->enableProductionReset();
        $admin = $this->admin();
        $this->mock(EncryptedBackupManager::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('create');
        });
        $payload = [
            'confirmation' => 'reset produksi',
            'reason' => 'Menyiapkan database untuk layanan produksi.',
            'current_password' => 'password',
        ];

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->actingAs($admin)->delete(route('admin.data-maintenance.production-reset'), $payload)
                ->assertSessionHasErrors('confirmation');
        }

        $this->actingAs($admin)->delete(route('admin.data-maintenance.production-reset'), $payload)
            ->assertTooManyRequests();

        config(['app.production_data_reset_rate_limit_enabled' => false]);
        $this->actingAs($admin)->delete(route('admin.data-maintenance.production-reset'), $payload)
            ->assertSessionHasErrors('confirmation');
    }

    public function test_failed_backup_leaves_production_data_untouched(): void
    {
        $this->enableProductionReset();
        $admin = $this->admin();
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081200009101']);
        $permit = $this->permit('MBG/SIK/X/0922', 'permits/id-docs/backup-failure.jpg');
        $this->mock(EncryptedBackupManager::class, function (MockInterface $mock): void {
            $mock->shouldReceive('create')->once()->andThrow(new RuntimeException('Backup gagal.'));
        });

        $this->actingAs($admin)->delete(route('admin.data-maintenance.production-reset'), [
            'confirmation' => 'RESET PRODUKSI',
            'reason' => 'Menyiapkan database untuk layanan produksi.',
            'current_password' => 'password',
        ])->assertRedirect()->assertSessionHasErrors('production_reset');

        $this->assertDatabaseHas('users', ['id' => $tenant->id]);
        $this->assertDatabaseHas('loading_permits', ['id' => $permit->id]);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'production_data_reset']);
    }

    public function test_backup_runtime_error_is_reported_without_deleting_production_data(): void
    {
        $this->enableProductionReset();
        $admin = $this->admin();
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081200009106']);
        $permit = $this->permit('MBG/SIK/X/0923', 'permits/id-docs/runtime-error.jpg');
        $this->mock(EncryptedBackupManager::class, function (MockInterface $mock): void {
            $mock->shouldReceive('create')->once()->andThrow(new \Error('Class "ZipArchive" not found'));
        });

        $this->actingAs($admin)->delete(route('admin.data-maintenance.production-reset'), [
            'confirmation' => 'RESET PRODUKSI',
            'reason' => 'Menyiapkan database untuk layanan produksi.',
            'current_password' => 'password',
        ])->assertRedirect()->assertSessionHasErrors('production_reset');

        $this->assertDatabaseHas('users', ['id' => $tenant->id]);
        $this->assertDatabaseHas('loading_permits', ['id' => $permit->id]);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'production_data_reset']);
    }

    public function test_production_reset_requires_current_password(): void
    {
        $this->enableProductionReset();
        $admin = $this->admin();
        $this->mock(EncryptedBackupManager::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('create');
        });

        $payload = [
            'confirmation' => 'RESET PRODUKSI',
            'reason' => 'Menyiapkan database untuk layanan produksi.',
            'current_password' => 'salah',
        ];
        $this->actingAs($admin)->delete(route('admin.data-maintenance.production-reset'), $payload)
            ->assertSessionHasErrors('current_password');

        $this->assertDatabaseMissing('audit_logs', ['event' => 'production_data_reset']);
    }

    public function test_production_reset_requires_recent_sensitive_confirmation(): void
    {
        $this->enableProductionReset();
        $admin = $this->admin();
        $this->mock(EncryptedBackupManager::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('create');
        });

        SecuritySetting::query()->findOrFail(1)->update(['sensitive_actions_require_2fa' => true]);
        $this->actingAs($admin)->delete(route('admin.data-maintenance.production-reset'), [
            'confirmation' => 'RESET PRODUKSI',
            'reason' => 'Menyiapkan database untuk layanan produksi.',
            'current_password' => 'password',
        ])
            ->assertSessionHasErrors('sensitive_action');

        $this->assertDatabaseMissing('audit_logs', ['event' => 'production_data_reset']);
    }

    public function test_production_reset_deletes_operational_data_and_preserves_configuration(): void
    {
        Storage::fake('local');
        $this->enableProductionReset();
        $admin = $this->admin();
        $otherAdmin = User::factory()->create(['role' => 'admin', 'phone' => '081200009102']);
        $tenantUser = User::factory()->create(['role' => 'tenant', 'phone' => '081200009103']);
        $validator = User::factory()->create(['role' => 'validator', 'division' => 'TR', 'phone' => '081200009104']);
        AuditLog::query()->create([
            'actor_id' => $validator->id,
            'event' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => $validator->id,
            'old_values' => [],
            'new_values' => ['fixture' => true],
        ]);
        DB::table('tenants')->insert([
            'name' => 'Tenant Dummy',
            'unit_number' => 'GF-01',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $permit = $this->permit('MBG/SIK/X/0923', 'permits/id-docs/reset.jpg');
        DB::table('permit_number_sequences')->insert([
            'permit_type' => 'loading',
            'period' => now()->format('Y'),
            'current_value' => 23,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);
        DB::table('whatsapp_settings')->insert([
            'base_url' => 'http://waha.internal',
            'api_key' => 'secret',
            'session' => 'default',
            'timeout' => 10,
            'is_active' => true,
            'notify_applicants' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('security_officers')->insert([
            'name' => 'Petugas Tetap',
            'officer_id' => 'SEC-001',
            'whatsapp' => '081299990001',
            'pin_hash' => 'hash',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_whatsapp_group_members')->insert([
            'name' => 'PIC Event',
            'phone' => '081299990002',
            'phone_hash' => hash('sha256', '081299990002'),
            'mention_by_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('document_templates')->insert([
            'permit_type' => 'loading',
            'version' => 1,
            'original_name' => 'loading.docx',
            'storage_path' => 'document-templates/loading/template.docx',
            'sha256' => hash('sha256', 'template'),
            'size' => 8,
            'placeholders' => '[]',
            'uploaded_by' => $admin->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Storage::disk('local')->put($permit->id_doc_path, 'identity');
        Storage::disk('local')->put('scanner/evidence/2026/11/dummy.jpg', 'evidence');
        Storage::disk('local')->put('document-templates/loading/template.docx', 'template');

        $this->mock(EncryptedBackupManager::class, function (MockInterface $mock): void {
            $mock->shouldReceive('create')->once()->andReturn(storage_path('app/backups/pre-reset.mbgbak'));
        });

        $this->actingAs($admin)->delete(route('admin.data-maintenance.production-reset'), [
            'confirmation' => 'RESET PRODUKSI',
            'reason' => 'Menyiapkan database untuk layanan produksi.',
            'current_password' => 'password',
        ])->assertRedirect(route('admin.data-maintenance.index'))
            ->assertSessionHas('production_reset_result');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id, 'role' => 'admin']);
        $this->assertDatabaseMissing('users', ['id' => $tenantUser->id]);
        $this->assertDatabaseMissing('users', ['id' => $validator->id]);
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('loading_permits', 0);
        $this->assertDatabaseCount('permit_number_sequences', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('whatsapp_settings', 1);
        $this->assertDatabaseCount('security_officers', 1);
        $this->assertDatabaseCount('event_whatsapp_group_members', 1);
        $this->assertDatabaseCount('document_templates', 1);
        $this->assertDatabaseHas('audit_logs', ['event' => 'production_data_reset', 'actor_id' => $admin->id]);
        Storage::disk('local')->assertMissing('permits');
        Storage::disk('local')->assertMissing('scanner/evidence');
        Storage::disk('local')->assertExists('document-templates/loading/template.docx');
        $this->assertTrue($this->app->make(AuditIntegrityService::class)->verify()['valid']);

        $this->expectException(ValidationException::class);
        $this->app->make(AdminDataMaintenanceService::class)->resetForProduction(
            $admin,
            'Percobaan reset kedua harus ditolak.',
            '',
        );
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'phone' => '081200009000']);
    }

    private function enableProductionReset(): void
    {
        config([
            'app.production_data_reset_enabled' => true,
            'backup.enabled' => true,
            'backup.encryption_key' => 'base64:'.base64_encode(str_repeat('B', 32)),
        ]);
    }

    private function permit(string $number, string $path): LoadingPermit
    {
        return LoadingPermit::query()->create([
            'permit_number' => $number,
            'tenant_name' => 'Tenant Uji',
            'applicant_name' => 'Pemohon Uji',
            'applicant_phone' => '081234567890',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'movement_time' => '22:00',
            'item_count' => 1,
            'item_unit' => 'koli',
            'item_description' => 'Data pengujian',
            'id_doc_path' => $path,
            'id_doc_type' => 'ktp',
            'status' => 'pending',
        ]);
    }
}
