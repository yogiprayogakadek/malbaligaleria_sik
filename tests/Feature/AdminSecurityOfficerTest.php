<?php

namespace Tests\Feature;

use App\Contracts\MalwareScanner;
use App\Http\Middleware\RequireSensitiveActionConfirmation;
use App\Jobs\SendWhatsappMessage;
use App\Models\AuditLog;
use App\Models\SecurityOfficer;
use App\Models\User;
use App\Models\WhatsappMessageLog;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AdminSecurityOfficerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(RequireSensitiveActionConfirmation::class);
        $this->app->instance(MalwareScanner::class, new class implements MalwareScanner
        {
            public function scan(UploadedFile $file, string $field): void {}
        });
    }

    public function test_admin_can_create_an_officer_and_pin_is_only_stored_as_a_hash(): void
    {
        Queue::fake();
        WhatsappSetting::query()->create($this->validWhatsappSetting());
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000090']);

        $response = $this->actingAs($admin)->post(route('admin.security-officers.store'), [
            'name' => 'Petugas Satu',
            'officer_id' => 'sec-001',
            'whatsapp' => '081234567890',
        ]);

        $response->assertRedirect(route('admin.security-officers.index'))
            ->assertSessionHas('generated_officer');
        $officer = SecurityOfficer::sole();
        $pin = session('generated_officer.pin');
        $this->assertMatchesRegularExpression('/\A\d{6}\z/', $pin);
        $this->assertSame('SEC-001', $officer->officer_id);
        $this->assertSame('6281234567890', $officer->whatsapp);
        $this->assertNotSame($pin, $officer->pin_hash);
        $this->assertTrue(Hash::check($pin, $officer->pin_hash));
        $audit = AuditLog::query()->where('auditable_type', SecurityOfficer::class)->latest('id')->firstOrFail();
        $this->assertSame('[dilindungi]', $audit->new_values['pin_hash']);
        $this->assertStringNotContainsString($pin, serialize($audit->new_values));
        $message = WhatsappMessageLog::sole();
        $this->assertSame('security_officer', $message->permit_type);
        $this->assertStringContainsString('Username / Nomor ID: SEC-001', $message->message);
        $this->assertStringContainsString("PIN: {$pin}", $message->message);
        $this->assertStringContainsString(route('scanner.index'), $message->message);
        $this->assertStringNotContainsString($pin, (string) DB::table('whatsapp_message_logs')->value('message'));
        Queue::assertPushed(SendWhatsappMessage::class, 1);
    }

    public function test_import_skips_existing_and_duplicate_rows_and_returns_a_one_time_report(): void
    {
        Queue::fake();
        WhatsappSetting::query()->create($this->validWhatsappSetting());
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000091']);
        SecurityOfficer::query()->create([
            'name' => 'Sudah Ada',
            'officer_id' => 'SEC-OLD',
            'whatsapp' => '628111111111',
            'pin_hash' => Hash::make('123456'),
            'is_active' => true,
        ]);

        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([
            ['Nama', 'Nomor ID Petugas', 'Nomor WhatsApp'],
            ['Sudah Ada', 'SEC-OLD', '08111111111'],
            ['Petugas Baru', 'SEC-NEW', '081222222222'],
            ['Duplikat Dalam File', 'SEC-NEW', '081333333333'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'security-officers-').'.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        try {
            $response = $this->actingAs($admin)->post(route('admin.security-officers.import'), [
                'officer_file' => new UploadedFile($path, 'petugas.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ]);

            $response->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $this->assertDatabaseCount('security_officers', 2);
            $this->assertDatabaseHas('security_officers', ['officer_id' => 'SEC-NEW']);
            $this->assertDatabaseCount('whatsapp_message_logs', 1);
            Queue::assertPushed(SendWhatsappMessage::class, 1);
        } finally {
            @unlink($path);
        }
    }

    public function test_non_admin_cannot_manage_security_officers(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081200000092']);

        $this->actingAs($tenant)->get(route('admin.security-officers.index'))->assertForbidden();
    }

    public function test_admin_can_view_an_officer_with_the_latest_credential_delivery_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000093']);
        $officer = SecurityOfficer::query()->create([
            'name' => 'Petugas Status',
            'officer_id' => 'SEC-STATUS',
            'whatsapp' => '6281234567001',
            'pin_hash' => Hash::make('123456'),
            'is_active' => true,
        ]);
        WhatsappMessageLog::query()->create([
            'unique_key' => 'security-officer-status-test',
            'permit_type' => 'security_officer',
            'permit_id' => $officer->id,
            'event' => 'credentials_created',
            'recipient_kind' => 'security_officer',
            'recipient_name' => $officer->name,
            'recipient_phone' => $officer->whatsapp,
            'recipient_phone_hash' => hash('sha256', $officer->whatsapp),
            'message' => 'Pesan akses scanner.',
            'status' => 'sent',
            'attempts' => 1,
            'sent_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.security-officers.index'))
            ->assertOk()
            ->assertSee('Petugas Status')
            ->assertSee('Akses terkirim')
            ->assertSee('data-security-officer-table', false)
            ->assertSee('officerDataTable', false)
            ->assertSee('Cari nama, ID, atau WhatsApp');
    }

    public function test_admin_can_control_security_officer_page_size(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000094']);

        $this->actingAs($admin)
            ->get(route('admin.security-officers.index', ['per_page' => 10]))
            ->assertOk()
            ->assertSee('officerPerPage', false);

        $this->actingAs($admin)
            ->get(route('admin.security-officers.index', ['per_page' => 500]))
            ->assertNotFound();
    }

    public function test_admin_can_resend_credentials_with_a_new_pin(): void
    {
        Queue::fake();
        WhatsappSetting::query()->create($this->validWhatsappSetting());
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000095']);
        $officer = SecurityOfficer::query()->create([
            'name' => 'Petugas Kirim Ulang',
            'officer_id' => 'SEC-RESEND',
            'whatsapp' => '6281234567000',
            'pin_hash' => Hash::make('111111'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.security-officers.reset-pin', $officer));

        $response->assertRedirect()->assertSessionHas('generated_officer');
        $newPin = session('generated_officer.pin');
        $this->assertNotSame('111111', $newPin);
        $this->assertTrue(Hash::check($newPin, $officer->fresh()->pin_hash));
        $message = WhatsappMessageLog::sole();
        $this->assertSame('credentials_resent', $message->event);
        $this->assertStringContainsString("PIN: {$newPin}", $message->message);
        Queue::assertPushed(SendWhatsappMessage::class, 1);
    }

    private function validWhatsappSetting(): array
    {
        return [
            'base_url' => 'http://192.168.0.253:3000',
            'api_key' => 'test-waha-api-key',
            'session' => 'default',
            'timeout' => 10,
            'is_active' => true,
            'notify_applicants' => true,
        ];
    }
}
