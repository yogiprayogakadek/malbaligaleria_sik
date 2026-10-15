<?php

namespace Tests\Feature;

use App\Events\LoadingPermitSubmitted;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PermitDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_loading_draft_is_encrypted_and_restored(): void
    {
        $response = $this->postJson(route('permit-drafts.store', 'loading'), [
            'tenant_name' => 'Tenant Draft Rahasia',
            'applicant_email' => 'draft@example.com',
            'direction' => 'in',
            'movement_time' => '22:30',
            'draft_step' => 2,
        ]);

        $response->assertOk()->assertJsonPath('message', 'Draft tersimpan.');
        $encrypted = session()->get('permit_drafts.loading.guest');

        $this->assertIsString($encrypted);
        $this->assertStringNotContainsString('Tenant Draft Rahasia', $encrypted);
        $this->assertStringNotContainsString('draft@example.com', $encrypted);

        $this->get(route('loading.create'))
            ->assertOk()
            ->assertSee('value="Tenant Draft Rahasia"', false)
            ->assertSee('value="draft@example.com"', false)
            ->assertSee('value="2"', false)
            ->assertSee('Draft dipulihkan. Lampiran perlu dipilih ulang.');
    }

    public function test_work_draft_restores_nested_and_conditional_fields_without_files_or_consent(): void
    {
        $this->postJson(route('permit-drafts.store', 'work'), [
            'contractor_name' => 'Kontraktor Draft',
            'workers' => [
                ['name' => 'Pekerja Satu', 'identity_number' => 'ID-100'],
                ['name' => '', 'identity_number' => ''],
            ],
            'work_schedules' => ['outside_store'],
            'work_category' => 'other',
            'is_rooftop_work' => true,
            'rooftop_network_type' => 'ac_cleaning',
            'consent' => true,
            'draft_step' => 4,
        ])->assertOk();

        $this->get(route('work-permits.create'))
            ->assertOk()
            ->assertSee('value="Kontraktor Draft"', false)
            ->assertSee('value="Pekerja Satu"', false)
            ->assertSee('value="ID-100"', false)
            ->assertSee('const restoredStep = Math.min(3', false)
            ->assertDontSee('name="consent" value="1" checked', false);
    }

    public function test_draft_rejects_files_and_does_not_accept_unknown_fields(): void
    {
        $this->postJson(route('permit-drafts.store', 'loading'), [
            'tenant_name' => 'Tenant Aman',
            'id_doc' => UploadedFile::fake()->image('ktp.jpg'),
            'admin_only' => 'tidak boleh tersimpan',
        ])->assertUnprocessable()->assertJsonValidationErrors('id_doc');

        $this->assertNull(session()->get('permit_drafts.loading.guest'));
    }

    public function test_authenticated_drafts_are_scoped_to_each_account(): void
    {
        $first = User::factory()->create([
            'role' => 'tenant',
            'tenant_name' => 'Tenant Pertama',
            'phone' => '081200000001',
        ]);
        $second = User::factory()->create([
            'role' => 'tenant',
            'tenant_name' => 'Tenant Kedua',
            'phone' => '081200000002',
        ]);

        $this->actingAs($first)
            ->postJson(route('permit-drafts.store', 'loading'), ['tenant_name' => 'Draft Milik Pertama'])
            ->assertOk();

        $this->actingAs($second)
            ->get(route('loading.create'))
            ->assertOk()
            ->assertSee('value="Tenant Kedua"', false)
            ->assertDontSee('Draft Milik Pertama');
    }

    public function test_successful_submission_clears_the_loading_draft(): void
    {
        Event::fake([LoadingPermitSubmitted::class]);
        Notification::fake();
        Storage::fake('local');

        $this->postJson(route('permit-drafts.store', 'loading'), [
            'tenant_name' => 'Tenant Selesai',
        ])->assertOk();

        $this->post(route('loading.store'), [
            'submission_token' => $this->submissionToken('loading'),
            'tenant_name' => 'Tenant Selesai',
            'applicant_name' => 'Pemohon Selesai',
            'applicant_phone' => '081234567890',
            'applicant_email' => null,
            'direction' => 'in',
            'movement_time' => '22:00',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'item_count' => 2,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian',
            'id_doc_type' => 'ktp',
            'id_doc' => UploadedFile::fake()->image('ktp.jpg', 800, 500),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull(session()->get('permit_drafts.loading.guest'));
    }

    public function test_draft_script_never_uses_browser_storage_and_excludes_sensitive_files(): void
    {
        $script = file_get_contents(public_path('permit-draft.js'));

        $this->assertIsString($script);
        $this->assertStringNotContainsString('localStorage', $script);
        $this->assertStringNotContainsString('sessionStorage', $script);
        $this->assertStringContainsString("data.delete('id_doc')", $script);
        $this->assertStringContainsString("data.delete('assignment_letter')", $script);
    }
}
