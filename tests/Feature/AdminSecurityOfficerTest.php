<?php

namespace Tests\Feature;

use App\Contracts\MalwareScanner;
use App\Http\Middleware\RequireSensitiveActionConfirmation;
use App\Models\AuditLog;
use App\Models\SecurityOfficer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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
    }

    public function test_import_skips_existing_and_duplicate_rows_and_returns_a_one_time_report(): void
    {
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
        } finally {
            @unlink($path);
        }
    }

    public function test_non_admin_cannot_manage_security_officers(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081200000092']);

        $this->actingAs($tenant)->get(route('admin.security-officers.index'))->assertForbidden();
    }
}
