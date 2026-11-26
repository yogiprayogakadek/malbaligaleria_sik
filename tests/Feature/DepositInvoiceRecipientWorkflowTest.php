<?php

namespace Tests\Feature;

use App\Jobs\GenerateDepositInvoice;
use App\Models\AuditLog;
use App\Models\DepositInvoiceSetting;
use App\Models\User;
use App\Models\WorkPermit;
use App\Services\DepositInvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class DepositInvoiceRecipientWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_no_deposit_decision_hides_and_does_not_store_recipient_metadata(): void
    {
        $mep = $this->validator('MEP');
        $permit = $this->permit(['status' => 'mep_review', 'deposit_required' => null, 'deposit_amount' => null]);

        $this->actingAs($mep)->get(route('staff.work-permits.show', $permit->public_token))
            ->assertOk()
            ->assertSee('Tujuan penagihan invoice')
            ->assertSee('data-deposit-recipient', false);

        $this->actingAs($mep)->post(route('mep.work-permits.deposit', $permit->public_token), [
            'deposit_required' => '0',
            'deposit_invoice_recipient_name' => 'PT Tidak Boleh Tersimpan',
            'deposit_invoice_recipient_address' => 'Jl. Tidak Boleh Tersimpan No. 1',
        ])->assertRedirect();

        $permit->refresh();
        $this->assertSame('approved', $permit->status);
        $this->assertFalse($permit->deposit_required);
        $this->assertNull($permit->deposit_invoice_recipient_name);
        $this->assertNull($permit->deposit_invoice_recipient_address);
        $this->assertNull($permit->deposit_invoice_recipient_source);
        $this->assertNull($permit->deposit_invoice_recipient_confirmed_by);
        $this->assertNull($permit->deposit_invoice_recipient_confirmed_at);
    }

    public function test_deposit_without_coordination_and_empty_fields_uses_application_fallback_without_address(): void
    {
        $this->enableAutomaticInvoices();
        $mep = $this->validator('MEP');
        $permit = $this->permit([
            'status' => 'mep_review',
            'deposit_required' => null,
            'deposit_amount' => null,
            'tenant_name' => 'Tenant Galeria',
            'contractor_name' => 'PT Kontraktor Karya',
        ]);

        $this->actingAs($mep)->post(route('mep.work-permits.deposit', $permit->public_token), [
            'deposit_required' => '1',
            'deposit_amount' => '3000000',
            'deposit_invoice_recipient_name' => "   \r\n  ",
            'deposit_invoice_recipient_address' => "  \n  ",
        ])->assertRedirect();

        $permit->refresh();
        $this->assertSame('awaiting_payment', $permit->status);
        $this->assertSame('application', $permit->deposit_invoice_recipient_source);
        $this->assertSame('Tenant Galeria / PT Kontraktor Karya', $permit->deposit_invoice_recipient_name);
        $this->assertNull($permit->deposit_invoice_recipient_address);
        $this->assertSame($mep->id, $permit->deposit_invoice_recipient_confirmed_by);
        $this->assertNotNull($permit->deposit_invoice_recipient_confirmed_at);

        $html = view('pdf.deposit-invoice', $this->pdfViewData($permit))->render();
        $this->assertStringContainsString('Tenant Galeria / PT Kontraktor Karya', $html);
        $this->assertStringNotContainsString('Alamat penagihan', $html);
        $this->assertStringContainsString($permit->applicant_name, $html);
        $this->assertStringContainsString($permit->permit_number, $html);
    }

    public function test_custom_recipient_without_coordination_is_normalized_and_used_in_automatic_invoice(): void
    {
        $this->enableAutomaticInvoices();
        $mep = $this->validator('MEP');
        $permit = $this->permit(['status' => 'mep_review', 'deposit_required' => null, 'deposit_amount' => null]);

        $this->actingAs($mep)->post(route('mep.work-permits.deposit', $permit->public_token), [
            'deposit_required' => '1',
            'deposit_amount' => '4500000',
            'deposit_invoice_recipient_name' => "  PT Mitra Sejahtera  \r\n",
            'deposit_invoice_recipient_address' => "  Jl. Bypass Ngurah Rai No. 88  \r\n  Kuta, Badung, Bali  \r\n ",
        ])->assertRedirect();

        $permit->refresh();
        $this->assertSame('awaiting_payment', $permit->status);
        $this->assertSame('custom', $permit->deposit_invoice_recipient_source);
        $this->assertSame('PT Mitra Sejahtera', $permit->deposit_invoice_recipient_name);
        $this->assertSame("Jl. Bypass Ngurah Rai No. 88\nKuta, Badung, Bali", $permit->deposit_invoice_recipient_address);
        $this->assertSame($mep->id, $permit->deposit_invoice_recipient_confirmed_by);
        $this->assertNotNull($permit->deposit_invoice_recipient_confirmed_at);
        Storage::disk('local')->assertExists($permit->deposit_invoice_path);

        $html = view('pdf.deposit-invoice', $this->pdfViewData($permit))->render();
        $this->assertStringContainsString('PT Mitra Sejahtera', $html);
        $this->assertStringContainsString('Alamat penagihan', $html);
        $this->assertStringContainsString('Jl. Bypass Ngurah Rai No. 88', $html);
        $this->assertStringContainsString('Kuta, Badung, Bali', $html);
    }

    public function test_partial_custom_input_uses_fallback_for_the_other_field(): void
    {
        $mep = $this->validator('MEP');
        $permit = $this->permit([
            'status' => 'mep_review',
            'deposit_required' => null,
            'deposit_amount' => null,
            'tenant_name' => 'Tenant A',
            'contractor_name' => 'Vendor B',
        ]);

        $this->actingAs($mep)->post(route('mep.work-permits.deposit', $permit->public_token), [
            'deposit_required' => '1',
            'deposit_amount' => '1500000',
            'deposit_invoice_recipient_name' => '',
            'deposit_invoice_recipient_address' => 'Jl. Sunset Road No. 9',
        ])->assertRedirect();

        $permit->refresh();
        $this->assertSame('awaiting_invoice', $permit->status);
        $this->assertSame('custom', $permit->deposit_invoice_recipient_source);
        $this->assertSame('Tenant A / Vendor B', $permit->deposit_invoice_recipient_name);
        $this->assertSame('Jl. Sunset Road No. 9', $permit->deposit_invoice_recipient_address);
        $this->assertNotNull($permit->deposit_invoice_recipient_confirmed_at);
    }

    public function test_coordination_stores_draft_and_dialog_defaults_and_allows_updating_before_confirm(): void
    {
        $this->enableAutomaticInvoices();
        $mep = $this->validator('MEP');

        $permitEmptyDraft = $this->permit([
            'status' => 'mep_review',
            'deposit_required' => null,
            'deposit_amount' => null,
            'tenant_name' => 'Tenant Alpha',
            'contractor_name' => 'Kontraktor Beta',
        ]);
        $this->actingAs($mep)->post(route('mep.work-permits.deposit', $permitEmptyDraft->public_token), [
            'coordination_required' => '1',
            'deposit_required' => '1',
            'deposit_amount' => '2000000',
        ])->assertRedirect();

        $permitEmptyDraft->refresh();
        $this->assertSame('revision_required', $permitEmptyDraft->status);
        $this->assertSame('application', $permitEmptyDraft->deposit_invoice_recipient_source);
        $this->assertNull($permitEmptyDraft->deposit_invoice_recipient_name);
        $this->assertNull($permitEmptyDraft->deposit_invoice_recipient_address);
        $this->assertNull($permitEmptyDraft->deposit_invoice_recipient_confirmed_at);

        $this->actingAs($mep)->get(route('staff.work-permits.show', $permitEmptyDraft->public_token))
            ->assertOk()
            ->assertSee('Konfirmasi tujuan penagihan invoice')
            ->assertSee('Gunakan data permohonan')
            ->assertSee('Tenant Alpha / Kontraktor Beta')
            ->assertSee('Alamat penagihan tidak tersedia dari form permohonan.')
            ->assertSee('value="application" checked', false);

        $permitCustomDraft = $this->permit([
            'status' => 'mep_review',
            'deposit_required' => null,
            'deposit_amount' => null,
        ]);
        $this->actingAs($mep)->post(route('mep.work-permits.deposit', $permitCustomDraft->public_token), [
            'coordination_required' => '1',
            'deposit_required' => '1',
            'deposit_amount' => '2500000',
            'deposit_invoice_recipient_name' => 'PT Draft Awal',
            'deposit_invoice_recipient_address' => 'Jl. Draft Lama No. 1',
        ])->assertRedirect();

        $permitCustomDraft->refresh();
        $this->assertSame('custom', $permitCustomDraft->deposit_invoice_recipient_source);
        $this->assertSame('PT Draft Awal', $permitCustomDraft->deposit_invoice_recipient_name);
        $this->assertSame('Jl. Draft Lama No. 1', $permitCustomDraft->deposit_invoice_recipient_address);
        $this->assertNull($permitCustomDraft->deposit_invoice_recipient_confirmed_at);

        $this->actingAs($mep)->get(route('staff.work-permits.show', $permitCustomDraft->public_token))
            ->assertOk()
            ->assertSee('value="custom" checked', false)
            ->assertSee('PT Draft Awal')
            ->assertSee('Jl. Draft Lama No. 1');

        $this->actingAs($mep)->post(route('mep.work-permits.coordination.complete', $permitCustomDraft->public_token), [
            'deposit_invoice_recipient_mode' => 'custom',
            'deposit_invoice_recipient_name' => 'PT Final Terkonfirmasi',
            'deposit_invoice_recipient_address' => "Jl. Final No. 12\nDenpasar",
        ])->assertRedirect();

        $permitCustomDraft->refresh();
        $this->assertSame('awaiting_payment', $permitCustomDraft->status);
        $this->assertSame('custom', $permitCustomDraft->deposit_invoice_recipient_source);
        $this->assertSame('PT Final Terkonfirmasi', $permitCustomDraft->deposit_invoice_recipient_name);
        $this->assertSame("Jl. Final No. 12\nDenpasar", $permitCustomDraft->deposit_invoice_recipient_address);
        $this->assertSame($mep->id, $permitCustomDraft->deposit_invoice_recipient_confirmed_by);
        $this->assertNotNull($permitCustomDraft->deposit_invoice_recipient_confirmed_at);
    }

    public function test_custom_mode_without_name_or_address_fails_validation_keeps_coordination_and_reopens_dialog(): void
    {
        $mep = $this->validator('MEP');
        $permit = $this->permit(['status' => 'mep_review', 'deposit_required' => null, 'deposit_amount' => null]);

        $this->actingAs($mep)->post(route('mep.work-permits.deposit', $permit->public_token), [
            'coordination_required' => '1',
            'deposit_required' => '1',
            'deposit_amount' => '2500000',
        ])->assertRedirect();

        $showUrl = route('staff.work-permits.show', $permit->public_token);

        $this->actingAs($mep)->from($showUrl)->post(route('mep.work-permits.coordination.complete', $permit->public_token), [
            'deposit_invoice_recipient_mode' => 'custom',
            'deposit_invoice_recipient_name' => '   ',
            'deposit_invoice_recipient_address' => " \r\n ",
        ])->assertRedirect($showUrl)->assertSessionHasErrors('deposit_invoice_recipient_name');

        $permit->refresh();
        $this->assertSame('revision_required', $permit->status);
        $this->assertNull($permit->deposit_invoice_recipient_confirmed_at);
        $this->assertNull($permit->revisions()->where('field_key', 'other_mep_coordination')->value('resolved_at'));

        $this->actingAs($mep)->get($showUrl)
            ->assertOk()
            ->assertSee('data-reopen-on-load="true"', false)
            ->assertSee('value="custom" checked', false);
    }

    public function test_active_editable_revision_keeps_recipient_as_unlocked_draft_and_does_not_issue_invoice(): void
    {
        $this->enableAutomaticInvoices();
        $mep = $this->validator('MEP');
        $permit = $this->permit(['status' => 'mep_review', 'deposit_required' => null, 'deposit_amount' => null]);

        $this->actingAs($mep)->post(route('mep.work-permits.deposit', $permit->public_token), [
            'has_revision' => '1',
            'fields' => ['work_type'],
            'reasons' => ['work_type' => 'Uraian pekerjaan harus diperinci lagi.'],
            'coordination_required' => '1',
            'deposit_required' => '1',
            'deposit_amount' => '2500000',
            'deposit_invoice_recipient_name' => 'PT Draft Revisi',
        ])->assertRedirect();

        $this->actingAs($mep)->post(route('mep.work-permits.coordination.complete', $permit->public_token), [
            'deposit_invoice_recipient_mode' => 'custom',
            'deposit_invoice_recipient_name' => 'PT Draft Diperbarui',
            'deposit_invoice_recipient_address' => 'Jl. Draft Baru No. 7',
        ])->assertRedirect();

        $permit->refresh();
        $this->assertSame('revision_required', $permit->status);
        $this->assertNull($permit->deposit_invoice_path);
        $this->assertNull($permit->deposit_invoice_recipient_confirmed_at);
        $this->assertSame('PT Draft Diperbarui', $permit->deposit_invoice_recipient_name);
        $this->assertSame('Jl. Draft Baru No. 7', $permit->deposit_invoice_recipient_address);
    }

    public function test_confirmed_recipient_cannot_be_changed_by_mep_finance_or_model_update(): void
    {
        $mep = $this->validator('MEP');
        $finance = $this->validator('FIN');
        $permit = $this->permit(['status' => 'mep_review', 'deposit_required' => null, 'deposit_amount' => null]);

        $this->actingAs($mep)->post(route('mep.work-permits.deposit', $permit->public_token), [
            'deposit_required' => '1',
            'deposit_amount' => '3000000',
            'deposit_invoice_recipient_name' => 'PT Terkunci',
            'deposit_invoice_recipient_address' => 'Jl. Terkunci No. 1',
        ])->assertRedirect();

        $permit->refresh();
        $this->assertSame('awaiting_invoice', $permit->status);
        $this->assertNotNull($permit->deposit_invoice_recipient_confirmed_at);

        $this->actingAs($mep)->post(route('mep.work-permits.deposit', $permit->public_token), [
            'deposit_required' => '1',
            'deposit_amount' => '3000000',
            'deposit_invoice_recipient_name' => 'PT Diubah MEP',
        ])->assertConflict();

        $this->actingAs($mep)->post(route('mep.work-permits.coordination.complete', $permit->public_token), [
            'deposit_invoice_recipient_mode' => 'custom',
            'deposit_invoice_recipient_name' => 'PT Diubah Koordinasi',
        ])->assertConflict();

        $this->actingAs($finance)->post(route('finance.work-permits.invoice', $permit->public_token), [
            'deposit_invoice' => UploadedFile::fake()->create('manual.pdf', 50, 'application/pdf'),
            'deposit_invoice_recipient_name' => 'PT Diubah Finance',
        ])->assertForbidden();

        $this->assertSame('PT Terkunci', $permit->fresh()->deposit_invoice_recipient_name);
        $this->assertSame('Jl. Terkunci No. 1', $permit->fresh()->deposit_invoice_recipient_address);

        $this->expectException(LogicException::class);
        $permit->fresh()->update(['deposit_invoice_recipient_name' => 'PT Direct Update']);
    }

    public function test_manual_mode_shows_readonly_snapshot_to_finance_and_keeps_uploaded_pdf_unchanged(): void
    {
        $mep = $this->validator('MEP');
        $finance = $this->validator('FIN');
        $secretary = User::factory()->create(['role' => 'secretary', 'phone' => '081200009901']);
        $tr = $this->validator('TR');
        $permit = $this->permit(['status' => 'mep_review', 'deposit_required' => null, 'deposit_amount' => null]);

        $this->actingAs($mep)->post(route('mep.work-permits.deposit', $permit->public_token), [
            'deposit_required' => '1',
            'deposit_amount' => '4000000',
            'deposit_invoice_recipient_name' => 'PT Finance Read Only',
            'deposit_invoice_recipient_address' => "Jl. Kartika Plaza No. 5\nKuta",
        ])->assertRedirect();

        $permit->refresh();
        $this->assertSame('awaiting_invoice', $permit->status);

        $this->actingAs($finance)->get(route('staff.work-permits.show', ['token' => $permit->public_token, 'workspace' => 'FIN']))
            ->assertOk()
            ->assertSee('Tujuan Penagihan Invoice')
            ->assertSee('PT Finance Read Only')
            ->assertSee('Jl. Kartika Plaza No. 5')
            ->assertSee('Final · Hanya baca')
            ->assertDontSee('name="deposit_invoice_recipient_name"', false)
            ->assertDontSee('name="deposit_invoice_recipient_address"', false);

        $this->actingAs($secretary)->get(route('staff.work-permits.show', $permit->public_token))
            ->assertOk()
            ->assertDontSee('Jl. Kartika Plaza No. 5');

        $this->actingAs($tr)->get(route('staff.work-permits.show', $permit->public_token))
            ->assertForbidden();

        $rawPdf = Pdf::loadHTML('<p>Invoice Manual Finance</p>')->output();
        $upload = UploadedFile::fake()->createWithContent('invoice-manual.pdf', $rawPdf);
        $expectedHash = hash('sha256', $rawPdf);

        $this->actingAs($finance)->post(route('finance.work-permits.invoice', $permit->public_token), [
            'deposit_invoice' => $upload,
        ])->assertRedirect();

        $permit->refresh();
        $this->assertSame('awaiting_payment', $permit->status);
        $this->assertSame('uploaded', $permit->deposit_invoice_source);
        $this->assertSame($expectedHash, $permit->deposit_invoice_sha256);
        $this->assertSame($rawPdf, Storage::disk('local')->get($permit->deposit_invoice_path));

        $signedStaff = URL::temporarySignedRoute('staff.work-permits.deposit-invoice', now()->addMinutes(5), ['token' => $permit->public_token]);
        $this->actingAs($finance)->get($signedStaff)->assertOk();
    }

    public function test_legacy_backlog_permit_without_recipient_metadata_issues_invoice_with_fallback(): void
    {
        $this->enableAutomaticInvoices();
        $permit = $this->permit([
            'status' => 'awaiting_invoice',
            'tenant_name' => 'Tenant Lama',
            'contractor_name' => 'Kontraktor Lama',
            'deposit_invoice_recipient_name' => null,
            'deposit_invoice_recipient_address' => null,
            'deposit_invoice_recipient_source' => null,
            'deposit_invoice_recipient_confirmed_by' => null,
            'deposit_invoice_recipient_confirmed_at' => null,
        ]);

        (new GenerateDepositInvoice($permit->id))->handle(
            app(DepositInvoiceService::class),
            app(\App\Services\WorkPermitWorkflowNotifier::class),
        );

        $permit->refresh();
        $this->assertSame('awaiting_payment', $permit->status);
        $this->assertSame('application', $permit->deposit_invoice_recipient_source);
        $this->assertSame('Tenant Lama / Kontraktor Lama', $permit->deposit_invoice_recipient_name);
        $this->assertNull($permit->deposit_invoice_recipient_address);
        $this->assertNotNull($permit->deposit_invoice_recipient_confirmed_at);
        Storage::disk('local')->assertExists($permit->deposit_invoice_path);
    }

    public function test_service_refuses_unconfirmed_draft_recipient_before_issuing(): void
    {
        $this->enableAutomaticInvoices();
        $permit = $this->permit([
            'status' => 'awaiting_invoice',
            'deposit_invoice_recipient_name' => 'PT Draft Belum Konfirmasi',
            'deposit_invoice_recipient_source' => 'custom',
            'deposit_invoice_recipient_confirmed_at' => null,
        ]);

        $this->expectException(RuntimeException::class);
        app(DepositInvoiceService::class)->issue($permit);
    }

    public function test_generator_failure_preserves_confirmed_snapshot_for_manual_fallback(): void
    {
        DepositInvoiceSetting::current()->update(['mode' => 'automatic']);
        $mep = $this->validator('MEP');
        $finance = $this->validator('FIN');
        $permit = $this->permit(['status' => 'mep_review', 'deposit_required' => null, 'deposit_amount' => null]);

        $this->actingAs($mep)->post(route('mep.work-permits.deposit', $permit->public_token), [
            'deposit_required' => '1',
            'deposit_amount' => '3500000',
            'deposit_invoice_recipient_name' => 'PT Fallback Manual',
            'deposit_invoice_recipient_address' => 'Jl. Fallback No. 3',
        ])->assertRedirect();

        $permit->refresh();
        $this->assertSame('awaiting_invoice', $permit->status);
        $this->assertNull($permit->deposit_invoice_path);
        $this->assertSame('PT Fallback Manual', $permit->deposit_invoice_recipient_name);
        $this->assertSame('Jl. Fallback No. 3', $permit->deposit_invoice_recipient_address);
        $this->assertNotNull($permit->deposit_invoice_recipient_confirmed_at);

        $this->actingAs($finance)->get(route('staff.work-permits.show', ['token' => $permit->public_token, 'workspace' => 'FIN']))
            ->assertOk()
            ->assertSee('PT Fallback Manual')
            ->assertSee('Jl. Fallback No. 3');
    }

    public function test_duplicate_coordination_completion_does_not_create_duplicate_invoice_and_audit_omits_full_address(): void
    {
        $this->enableAutomaticInvoices();
        $mep = $this->validator('MEP');
        $permit = $this->permit(['status' => 'mep_review', 'deposit_required' => null, 'deposit_amount' => null]);
        $secretAddress = 'Jl. Rahasia Penagihan No. 999 Lantai 5 Kuta';

        $this->actingAs($mep)->post(route('mep.work-permits.deposit', $permit->public_token), [
            'coordination_required' => '1',
            'deposit_required' => '1',
            'deposit_amount' => '2500000',
            'deposit_invoice_recipient_name' => 'PT Audit Bersih',
            'deposit_invoice_recipient_address' => $secretAddress,
        ])->assertRedirect();

        $this->actingAs($mep)->post(route('mep.work-permits.coordination.complete', $permit->public_token), [
            'deposit_invoice_recipient_mode' => 'custom',
            'deposit_invoice_recipient_name' => 'PT Audit Bersih',
            'deposit_invoice_recipient_address' => $secretAddress,
        ])->assertRedirect();

        $this->actingAs($mep)->post(route('mep.work-permits.coordination.complete', $permit->public_token), [
            'deposit_invoice_recipient_mode' => 'custom',
            'deposit_invoice_recipient_name' => 'PT Audit Bersih',
            'deposit_invoice_recipient_address' => $secretAddress,
        ])->assertConflict();

        $permit->refresh();
        $this->assertSame(1, $permit->statusLogs()->where('action', 'deposit_invoice_generated')->count());

        $confirmationAudit = AuditLog::query()
            ->where('event', 'deposit_invoice_recipient_confirmed')
            ->where('auditable_id', $permit->id)
            ->first();
        $this->assertNotNull($confirmationAudit);
        $this->assertSame($mep->id, $confirmationAudit->actor_id);
        $this->assertSame('custom', $confirmationAudit->new_values['source'] ?? null);
        $this->assertTrue($confirmationAudit->new_values['has_address'] ?? false);
        $this->assertNotEmpty($confirmationAudit->new_values['confirmed_at'] ?? null);

        foreach (AuditLog::query()->where('auditable_id', $permit->id)->get() as $auditLog) {
            $encoded = json_encode([$auditLog->old_values, $auditLog->new_values]);
            $this->assertStringNotContainsString($secretAddress, (string) $encoded);
        }

        foreach ($permit->statusLogs()->get() as $statusLog) {
            $encoded = json_encode([$statusLog->notes, $statusLog->metadata]);
            $this->assertStringNotContainsString($secretAddress, (string) $encoded);
        }
    }

    public function test_deposit_and_refund_amounts_accept_formatted_rupiah_input_and_reject_invalid_strings(): void
    {
        $mep = $this->validator('MEP');
        $permit = $this->permit(['status' => 'mep_review', 'deposit_required' => null, 'deposit_amount' => null]);

        $this->actingAs($mep)->get(route('staff.work-permits.show', $permit->public_token))
            ->assertOk()
            ->assertSee('data-rupiah-input', false)
            ->assertSee('placeholder="Rp 5.000.000"', false);

        $this->actingAs($mep)->post(route('mep.work-permits.deposit', $permit->public_token), [
            'deposit_required' => '1',
            'deposit_amount' => 'bukan-nominal',
        ])->assertSessionHasErrors('deposit_amount');

        $this->actingAs($mep)->post(route('mep.work-permits.deposit', $permit->public_token), [
            'deposit_required' => '1',
            'deposit_amount' => 'Rp 5.250.000',
        ])->assertRedirect();

        $permit->refresh();
        $this->assertSame('awaiting_invoice', $permit->status);
        $this->assertSame('5250000.00', $permit->deposit_amount);

        $refundPermit = $this->permit([
            'status' => 'refund_processing',
            'deposit_required' => true,
            'deposit_amount' => 5250000,
        ]);
        $this->actingAs($mep)->post(route('mep.work-permits.refund.finish', $refundPermit->public_token), [
            'refund_amount' => 'Rp 2.500.000',
            'refund_proof' => UploadedFile::fake()->create('refund.pdf', 80, 'application/pdf'),
        ])->assertRedirect();

        $refundPermit->refresh();
        $this->assertSame('refunded', $refundPermit->status);
        $this->assertSame('2500000.00', $refundPermit->refund_amount);
        $this->assertSame('Rp 5.250.000', WorkPermit::formatRupiahInput('5250000.00'));
    }

    private function enableAutomaticInvoices(): void
    {
        $setting = DepositInvoiceSetting::current();
        $setting->update(['mode' => 'automatic']);
        $setting->accounts()->create([
            'bank_name' => 'Bank A',
            'account_number' => '1234567890',
            'account_holder' => 'PT Pengelola',
            'sort_order' => 0,
        ]);
    }

    private function validator(string $division): User
    {
        return User::factory()->create([
            'role' => 'validator',
            'division' => $division,
            'phone' => '08120000'.random_int(1000, 9999),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function permit(array $attributes = []): WorkPermit
    {
        return WorkPermit::query()->create(array_merge([
            'permit_kind' => 'work',
            'public_token' => Str::random(64),
            'applicant_token' => Str::random(64),
            'permit_number' => 'MBG/RCP/'.Str::upper(Str::random(8)),
            'contractor_name' => 'Kontraktor Uji',
            'tenant_name' => 'Tenant Uji',
            'applicant_name' => 'Pemohon Uji',
            'applicant_phone' => '081234567890',
            'applicant_email' => 'recipient@example.test',
            'work_location' => 'Unit GF-15',
            'work_category' => 'other',
            'assigned_division' => 'MEP',
            'work_type' => 'Instalasi listrik tambahan',
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
            'id_doc_path' => 'permits/work/id-docs/test.jpg',
            'status' => 'awaiting_invoice',
        ], $attributes));
    }

    /** @return array<string, mixed> */
    private function pdfViewData(WorkPermit $permit): array
    {
        $setting = DepositInvoiceSetting::current()->load('accounts');

        return [
            'permit' => $permit,
            'setting' => $setting,
            'accounts' => $setting->accounts,
            'invoiceNumber' => $permit->deposit_invoice_number ?: 'DEP/2026/11/0001',
            'issuedAt' => $permit->deposit_invoice_issued_at ?: now(),
            'dueDate' => ($permit->deposit_invoice_due_date ?: now())->toDateString(),
            'logoDataUri' => 'data:image/png;base64,',
            'documentId' => $permit->deposit_invoice_document_id ?: (string) Str::uuid(),
            'issuerName' => $setting->issuer_name,
            'issuerTitle' => $setting->issuer_title,
            'signatureMethod' => $setting->signature_method,
            'signatureDataUri' => 'data:image/svg+xml;base64,',
            'recipientName' => $permit->resolvedDepositInvoiceRecipientName(),
            'recipientAddress' => $permit->deposit_invoice_recipient_address,
        ];
    }
}
