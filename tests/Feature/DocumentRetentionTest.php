<?php

namespace Tests\Feature;

use App\Models\DocumentRetentionSetting;
use App\Models\LoadingPermit;
use App\Models\User;
use App\Models\WorkPermit;
use App\Services\SensitiveDocumentRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_manage_retention_policy_and_password_is_required(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081234567801']);
        $admin = User::factory()->create([
            'role' => 'admin',
            'phone' => '081234567802',
            'password' => 'password',
        ]);

        $this->get(route('admin.settings.retention.edit'))->assertRedirect(route('login'));
        $this->actingAs($tenant)->get(route('admin.settings.retention.edit'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.settings.retention.edit'))
            ->assertOk()
            ->assertSee('Masa simpan dokumen sensitif');

        $this->actingAs($admin)->put(route('admin.settings.retention.update'), [
            'enabled' => '1',
            'identity_days' => 14,
            'assignment_days' => 30,
            'financial_days' => 180,
            'current_password' => 'salah',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($admin)->put(route('admin.settings.retention.update'), [
            'enabled' => '1',
            'identity_days' => 14,
            'assignment_days' => 30,
            'financial_days' => 180,
            'current_password' => 'password',
        ])->assertRedirect(route('admin.settings.retention.edit'));

        $this->assertDatabaseHas('document_retention_settings', [
            'id' => 1,
            'enabled' => true,
            'identity_days' => 14,
            'assignment_days' => 30,
            'financial_days' => 180,
        ]);
    }

    public function test_expired_loading_identity_is_deleted_but_permit_record_remains(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('permits/id-docs/old.jpg', 'private');
        DocumentRetentionSetting::query()->findOrFail(1)->update(['identity_days' => 30]);
        $permit = LoadingPermit::query()->create($this->loadingData([
            'end_date' => now()->subDays(31)->toDateString(),
            'id_doc_path' => 'permits/id-docs/old.jpg',
            'status' => 'approved',
        ]));

        $result = app(SensitiveDocumentRetentionService::class)->run();

        $this->assertSame(1, $result['purged']);
        Storage::disk('local')->assertMissing('permits/id-docs/old.jpg');
        $this->assertNull($permit->fresh()->id_doc_path);
        $this->assertDatabaseHas('loading_permits', ['id' => $permit->id]);
    }

    public function test_active_and_recent_documents_are_not_deleted(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('permits/id-docs/pending.jpg', 'private');
        Storage::disk('local')->put('permits/id-docs/recent.jpg', 'private');
        $pending = LoadingPermit::query()->create($this->loadingData([
            'permit_number' => 'MBG/SIK/RET/0002',
            'id_doc_path' => 'permits/id-docs/pending.jpg',
            'status' => 'pending',
        ]));
        $recent = LoadingPermit::query()->create($this->loadingData([
            'permit_number' => 'MBG/SIK/RET/0003',
            'id_doc_path' => 'permits/id-docs/recent.jpg',
            'status' => 'approved',
            'end_date' => now()->subDays(5)->toDateString(),
        ]));

        $result = app(SensitiveDocumentRetentionService::class)->run();

        $this->assertSame(0, $result['eligible']);
        $this->assertNotNull($pending->fresh()->id_doc_path);
        $this->assertNotNull($recent->fresh()->id_doc_path);
    }

    public function test_deposit_documents_wait_until_refund_is_final(): void
    {
        Storage::fake('local');
        DocumentRetentionSetting::query()->findOrFail(1)->update([
            'identity_days' => 1,
            'financial_days' => 1,
        ]);
        foreach (['id.jpg', 'payment.pdf'] as $file) {
            $directory = $file === 'id.jpg' ? 'permits/work/id-docs/' : 'permits/work/payment-proofs/';
            Storage::disk('local')->put($directory.$file, 'private');
        }
        $permit = WorkPermit::query()->create($this->workData([
            'status' => 'completed',
            'deposit_required' => true,
            'completed_at' => now()->subDays(10),
            'id_doc_path' => 'permits/work/id-docs/id.jpg',
            'payment_proof_path' => 'permits/work/payment-proofs/payment.pdf',
        ]));

        $this->assertSame(0, app(SensitiveDocumentRetentionService::class)->run()['eligible']);

        $permit->update([
            'status' => 'refunded',
            'refund_processed_at' => now()->subDays(2),
        ]);
        $result = app(SensitiveDocumentRetentionService::class)->run();

        $this->assertSame(2, $result['purged']);
        $this->assertNull($permit->fresh()->id_doc_path);
        $this->assertNull($permit->fresh()->payment_proof_path);
    }

    public function test_dry_run_and_disabled_command_do_not_delete_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('permits/id-docs/dry.jpg', 'private');
        $permit = LoadingPermit::query()->create($this->loadingData([
            'end_date' => now()->subDays(40)->toDateString(),
            'id_doc_path' => 'permits/id-docs/dry.jpg',
            'status' => 'approved',
        ]));

        $this->artisan('permits:purge-sensitive-documents --dry-run')->assertSuccessful();
        $this->assertNotNull($permit->fresh()->id_doc_path);

        DocumentRetentionSetting::query()->findOrFail(1)->update(['enabled' => false]);
        $this->artisan('permits:purge-sensitive-documents')->assertSuccessful();
        Storage::disk('local')->assertExists('permits/id-docs/dry.jpg');
    }

    public function test_unexpected_storage_path_is_never_deleted(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('unrelated/private.txt', 'keep');
        $permit = LoadingPermit::query()->create($this->loadingData([
            'end_date' => now()->subDays(40)->toDateString(),
            'id_doc_path' => 'permits/id-docs/../../unrelated/private.txt',
            'status' => 'approved',
        ]));

        $result = app(SensitiveDocumentRetentionService::class)->run();

        $this->assertSame(1, $result['failed']);
        Storage::disk('local')->assertExists('unrelated/private.txt');
        $this->assertSame('permits/id-docs/../../unrelated/private.txt', $permit->fresh()->id_doc_path);
    }

    /** @param array<string, mixed> $overrides */
    private function loadingData(array $overrides = []): array
    {
        return array_merge([
            'permit_number' => 'MBG/SIK/RET/0001',
            'tenant_name' => 'Tenant Retensi',
            'applicant_name' => 'Pemohon Retensi',
            'applicant_phone' => '081234567890',
            'direction' => 'in',
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
            'item_count' => 1,
            'item_unit' => 'koli',
            'item_description' => 'Barang',
            'id_doc_path' => 'permits/id-docs/default.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'approved',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function workData(array $overrides = []): array
    {
        return array_merge([
            'contractor_name' => 'Kontraktor Retensi',
            'applicant_name' => 'Pemohon Retensi',
            'applicant_phone' => '081234567890',
            'work_location' => 'Unit GF-12',
            'work_category' => 'other',
            'assigned_division' => 'MEP',
            'work_type' => 'Perbaikan',
            'work_schedule' => 'inside_store',
            'work_schedules' => ['inside_store'],
            'start_date' => now()->subDays(12)->toDateString(),
            'end_date' => now()->subDays(10)->toDateString(),
            'needs_water' => false,
            'security_deposit' => true,
            'id_doc_type' => 'ktp',
            'id_doc_path' => 'permits/work/id-docs/default.jpg',
            'status' => 'completed',
        ], $overrides);
    }
}
