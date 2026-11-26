<?php

namespace Tests\Feature;

use App\Contracts\MalwareScanner;
use App\Jobs\GenerateDepositInvoice;
use App\Models\AuditLog;
use App\Models\DepositInvoiceSetting;
use App\Models\User;
use App\Models\WorkPermit;
use App\Services\DepositInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DepositInvoiceAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_admin_configures_automatic_mode_with_multiple_encrypted_accounts_and_queues_backlog(): void
    {
        Queue::fake();
        $admin = User::factory()->create([
            'role' => 'admin',
            'phone' => '081200008801',
            'password' => Hash::make('secret-password'),
        ]);
        $backlog = $this->permit();

        $this->actingAs($admin)->put(route('admin.settings.deposit-invoices.update'), [
            'mode' => 'automatic',
            'issuer_name' => 'PT Pengelola MBG',
            'issuer_title' => 'Finance Property Management',
            'signature_method' => 'barcode',
            'payment_instructions' => 'Cantumkan nomor invoice pada berita transfer.',
            'accounts' => [
                ['bank_name' => 'Bank A', 'account_number' => '1234567890', 'account_holder' => 'PT Pengelola'],
                ['bank_name' => 'Bank B', 'account_number' => '9988776655', 'account_holder' => 'PT Pengelola'],
            ],
            'current_password' => 'secret-password',
        ])->assertRedirect(route('admin.settings.deposit-invoices.edit'));

        $setting = DepositInvoiceSetting::current()->load('accounts');
        $this->assertTrue($setting->isAutomatic());
        $this->assertSame('PT Pengelola MBG', $setting->issuer_name);
        $this->assertSame('barcode', $setting->signature_method);
        $this->assertCount(2, $setting->accounts);
        $this->assertSame('1234567890', $setting->accounts->first()->account_number);
        $this->assertNotSame('1234567890', $setting->accounts->first()->getRawOriginal('account_number'));
        $this->assertTrue(AuditLog::query()->where('auditable_type', $setting->accounts->first()::class)->get()
            ->contains(fn (AuditLog $log): bool => ($log->new_values['account_number'] ?? null) === '[dilindungi]'));
        Queue::assertPushed(GenerateDepositInvoice::class, fn (GenerateDepositInvoice $job): bool => $job->permitId === $backlog->id);

        $this->get(route('admin.settings.deposit-invoices.edit'))
            ->assertOk()
            ->assertSee('Pengaturan invoice deposit')
            ->assertSee('Bank A')
            ->assertSee('Bank B');
    }

    public function test_automatic_invoices_are_generated_once_with_unique_numbers_and_verified_hashes(): void
    {
        $setting = DepositInvoiceSetting::current();
        $setting->update(['mode' => 'automatic', 'payment_instructions' => 'Bayar sebelum pekerjaan dimulai.']);
        $setting->accounts()->create([
            'bank_name' => 'Bank A',
            'account_number' => '1234567890',
            'account_holder' => 'PT Pengelola',
            'sort_order' => 0,
        ]);
        $first = $this->permit(['start_date' => now()->subDay()->toDateString()]);
        $second = $this->permit();
        $service = app(DepositInvoiceService::class);

        $issued = $service->issue($first);
        $same = $service->issue($issued);
        $other = $service->issue($second);

        $this->assertSame('awaiting_payment', $issued->status);
        $this->assertSame('generated', $issued->deposit_invoice_source);
        $this->assertTrue($issued->wasChanged('deposit_invoice_path'));
        $this->assertFalse($same->wasChanged('deposit_invoice_path'));
        $this->assertNotNull($issued->deposit_invoice_issued_at);
        $this->assertNotNull($issued->deposit_invoice_document_id);
        $this->assertSame('Mal Bali Galeria', $issued->deposit_invoice_issuer_name);
        $this->assertSame('barcode', $issued->deposit_invoice_signature_method);
        $this->assertSame(now()->toDateString(), $issued->deposit_invoice_due_date->toDateString());
        $this->assertSame($issued->deposit_invoice_number, $same->deposit_invoice_number);
        $this->assertNotSame($issued->deposit_invoice_number, $other->deposit_invoice_number);
        $this->assertMatchesRegularExpression('#^DEP/\d{4}/\d{2}/\d{4}$#', $issued->deposit_invoice_number);
        Storage::disk('local')->assertExists($issued->deposit_invoice_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($issued->deposit_invoice_path));
        $this->assertSame(1, $issued->statusLogs()->where('action', 'deposit_invoice_generated')->count());
        $service->assertIntegrity($issued);

        $this->get(route('deposit-invoices.verify', $issued->deposit_invoice_document_id))
            ->assertOk()
            ->assertSee($issued->deposit_invoice_number)
            ->assertSee('Invoice tercatat dan utuh');

        Storage::disk('local')->put($issued->deposit_invoice_path, 'tampered');
        $this->get(route('deposit-invoices.verify', $issued->deposit_invoice_document_id))
            ->assertStatus(409)
            ->assertSee('Integritas invoice tidak valid');
        try {
            $service->assertIntegrity($issued);
            $this->fail('Invoice yang berubah seharusnya ditolak.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_mep_deposit_decision_immediately_issues_an_automatic_invoice(): void
    {
        $setting = DepositInvoiceSetting::current();
        $setting->update(['mode' => 'automatic']);
        $setting->accounts()->create([
            'bank_name' => 'Bank A',
            'account_number' => '1234567890',
            'account_holder' => 'PT Pengelola',
            'sort_order' => 0,
        ]);
        $mep = User::factory()->create([
            'role' => 'validator',
            'division' => 'MEP',
            'phone' => '081200008803',
        ]);
        $permit = $this->permit([
            'status' => 'mep_review',
            'security_deposit' => false,
            'deposit_required' => null,
            'deposit_amount' => null,
            'deposit_set_at' => null,
        ]);

        $this->actingAs($mep)->post(route('mep.work-permits.deposit', $permit->public_token), [
            'deposit_required' => '1',
            'deposit_amount' => '2500000',
            'deposit_notes' => 'Deposit pekerjaan instalasi.',
        ])->assertRedirect();

        $permit->refresh();
        $this->assertSame('awaiting_payment', $permit->status);
        $this->assertSame('generated', $permit->deposit_invoice_source);
        $this->assertNotNull($permit->deposit_invoice_number);
        Storage::disk('local')->assertExists($permit->deposit_invoice_path);
    }

    public function test_coordination_completion_issues_one_automatic_invoice(): void
    {
        $setting = DepositInvoiceSetting::current();
        $setting->update(['mode' => 'automatic']);
        $setting->accounts()->create([
            'bank_name' => 'Bank A',
            'account_number' => '1234567890',
            'account_holder' => 'PT Pengelola',
            'sort_order' => 0,
        ]);
        $mep = User::factory()->create([
            'role' => 'validator',
            'division' => 'MEP',
            'phone' => '081200008804',
        ]);
        $permit = $this->permit([
            'status' => 'mep_review',
            'security_deposit' => false,
            'deposit_required' => null,
            'deposit_amount' => null,
            'deposit_set_at' => null,
        ]);

        $this->actingAs($mep)->post(route('mep.work-permits.deposit', $permit->public_token), [
            'coordination_required' => '1',
            'coordination_notes' => '',
            'deposit_required' => '1',
            'deposit_amount' => '2500000',
        ])->assertRedirect();
        $this->assertSame('revision_required', $permit->fresh()->status);

        $this->post(route('mep.work-permits.coordination.complete', $permit->public_token))->assertRedirect();
        $permit->refresh();
        $this->assertSame('awaiting_payment', $permit->status);
        $this->assertSame('generated', $permit->deposit_invoice_source);
        $this->assertSame(1, $permit->statusLogs()->where('action', 'deposit_invoice_generated')->count());

        $this->post(route('mep.work-permits.coordination.complete', $permit->public_token))->assertConflict();
        $this->assertSame(1, $permit->statusLogs()->where('action', 'deposit_invoice_generated')->count());
    }

    public function test_automatic_mode_requires_at_least_one_complete_account(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'phone' => '081200008802',
            'password' => Hash::make('secret-password'),
        ]);

        $this->actingAs($admin)->from(route('admin.settings.deposit-invoices.edit'))->put(route('admin.settings.deposit-invoices.update'), [
            'mode' => 'automatic',
            'issuer_name' => 'Mal Bali Galeria',
            'issuer_title' => 'Property Management',
            'signature_method' => 'barcode',
            'accounts' => [],
            'current_password' => 'secret-password',
        ])->assertRedirect(route('admin.settings.deposit-invoices.edit'))->assertSessionHasErrors('accounts');

        $this->assertSame('manual', DepositInvoiceSetting::current()->mode);
    }

    public function test_admin_can_upload_private_signature_and_generated_invoice_snapshots_issuer(): void
    {
        $this->app->instance(MalwareScanner::class, new class implements MalwareScanner
        {
            public function scan(UploadedFile $file, string $field): void {}
        });
        $admin = User::factory()->create([
            'role' => 'admin',
            'phone' => '081200008805',
            'password' => Hash::make('secret-password'),
        ]);

        $this->actingAs($admin)->put(route('admin.settings.deposit-invoices.update'), [
            'mode' => 'automatic',
            'issuer_name' => 'Nama Penerbit Invoice',
            'issuer_title' => 'Finance Mal Bali Galeria',
            'signature_method' => 'upload',
            'signature_image' => UploadedFile::fake()->image('signature.png', 320, 120),
            'accounts' => [
                ['bank_name' => 'Bank A', 'account_number' => '1234567890', 'account_holder' => 'PT Pengelola'],
            ],
            'current_password' => 'secret-password',
        ])->assertRedirect(route('admin.settings.deposit-invoices.edit'));

        $setting = DepositInvoiceSetting::current();
        $this->assertSame('upload', $setting->signature_method);
        $this->assertNotNull($setting->signature_path);
        $this->assertNotNull($setting->signature_sha256);
        Storage::disk('local')->assertExists($setting->signature_path);
        $this->assertTrue(AuditLog::query()->where('auditable_type', DepositInvoiceSetting::class)->get()
            ->contains(fn (AuditLog $log): bool => ($log->new_values['signature_path'] ?? null) === '[dilindungi]'));

        $issued = app(DepositInvoiceService::class)->issue($this->permit());
        $this->assertSame('Nama Penerbit Invoice', $issued->deposit_invoice_issuer_name);
        $this->assertSame('Finance Mal Bali Galeria', $issued->deposit_invoice_issuer_title);
        $this->assertSame('upload', $issued->deposit_invoice_signature_method);

        $setting->update(['issuer_name' => 'Penerbit Baru']);
        $this->assertSame('Nama Penerbit Invoice', $issued->fresh()->deposit_invoice_issuer_name);
    }

    public function test_uploaded_signature_method_requires_an_image(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'phone' => '081200008806',
            'password' => Hash::make('secret-password'),
        ]);

        $this->actingAs($admin)->from(route('admin.settings.deposit-invoices.edit'))->put(route('admin.settings.deposit-invoices.update'), [
            'mode' => 'manual',
            'issuer_name' => 'Mal Bali Galeria',
            'issuer_title' => 'Property Management',
            'signature_method' => 'upload',
            'current_password' => 'secret-password',
        ])->assertRedirect(route('admin.settings.deposit-invoices.edit'))->assertSessionHasErrors('signature_image');
    }

    public function test_generation_failure_keeps_manual_fallback_and_records_audit(): void
    {
        DepositInvoiceSetting::current()->update(['mode' => 'automatic']);
        $permit = $this->permit();

        try {
            app(DepositInvoiceService::class)->issue($permit);
            $this->fail('Generator tanpa rekening seharusnya gagal.');
        } catch (RuntimeException) {
            $permit->refresh();
            $this->assertSame('awaiting_invoice', $permit->status);
            $this->assertNull($permit->deposit_invoice_path);
            $this->assertTrue($permit->statusLogs()->where('action', 'deposit_invoice_generation_failed')->exists());
            $this->assertTrue(AuditLog::query()->where('event', 'deposit_invoice_generation_failed')->where('auditable_id', $permit->id)->exists());
        }
    }

    private function permit(array $attributes = []): WorkPermit
    {
        return WorkPermit::query()->create(array_merge([
            'permit_kind' => 'work',
            'public_token' => Str::random(64),
            'applicant_token' => Str::random(64),
            'permit_number' => 'MBG/INV/'.Str::upper(Str::random(8)),
            'contractor_name' => 'Kontraktor Invoice',
            'tenant_name' => 'Tenant Invoice',
            'applicant_name' => 'Pemohon Invoice',
            'applicant_phone' => '081234567890',
            'applicant_email' => 'invoice@example.test',
            'work_location' => 'Unit GF-12',
            'work_category' => 'other',
            'assigned_division' => 'MEP',
            'work_type' => 'Perbaikan instalasi',
            'work_schedule' => 'inside_store',
            'work_schedules' => ['inside_store'],
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'needs_water' => false,
            'security_deposit' => true,
            'deposit_required' => true,
            'deposit_amount' => 2500000,
            'deposit_set_at' => now(),
            'id_doc_type' => 'ktp',
            'id_doc_path' => 'permits/work/id-docs/invoice.jpg',
            'status' => 'awaiting_invoice',
        ], $attributes));
    }
}
