<?php

namespace Tests\Feature;

use App\Events\LoadingPermitSubmitted;
use App\Events\WorkPermitWorkflowUpdated;
use App\Models\AuditLog;
use App\Models\LoadingPermit;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\WorkPermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminOperationalControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_edit_a_message_template_without_server_error(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.message-templates.store'), [
            'key' => 'custom_queue_notice',
            'name' => 'Pengingat khusus',
            'channel' => 'whatsapp',
            'subject' => null,
            'body' => 'Permohonan {{permit_number}} menunggu {{division}}.',
            'allowed_variables' => ['permit_number', 'division'],
            'is_active' => 1,
            'current_password' => 'password',
        ])->assertRedirect(route('admin.message-templates.index'));

        $template = MessageTemplate::query()->where('key', 'custom_queue_notice')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.message-templates.update', $template), [
            'key' => $template->key,
            'name' => 'Pengingat antrean khusus',
            'body' => 'Nomor {{permit_number}} perlu diperiksa.',
            'allowed_variables' => ['permit_number'],
            'is_active' => 1,
            'current_password' => 'password',
        ])->assertRedirect(route('admin.message-templates.index'));

        $this->assertSame('Pengingat antrean khusus', $template->fresh()->name);

        $systemTemplate = MessageTemplate::query()->where('key', 'system_health_alert')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.message-templates.update', $systemTemplate), [
            'key' => $systemTemplate->key,
            'name' => $systemTemplate->name,
            'subject' => 'Gangguan {{component}}',
            'body' => '{{detail}} terdeteksi pada {{detected_at}}.',
            'allowed_variables' => ['component', 'detail', 'detected_at'],
            'is_active' => 1,
            'current_password' => 'password',
        ])->assertRedirect(route('admin.message-templates.index'));
    }

    public function test_manual_queue_reminder_only_targets_the_responsible_validator(): void
    {
        Event::fake([LoadingPermitSubmitted::class, WorkPermitWorkflowUpdated::class]);
        Notification::fake();
        $admin = $this->admin();
        $tr = User::factory()->create(['role' => 'validator', 'division' => 'TR', 'is_active' => true, 'phone' => '081200008002']);
        $mep = User::factory()->create(['role' => 'validator', 'division' => 'MEP', 'is_active' => true, 'phone' => '081200008003']);
        $permit = $this->loadingPermit();

        $this->actingAs($admin)->post(route('admin.queue-reminders.store'), [
            'selection_mode' => 'selected',
            'queue_items' => ['loading:'.$permit->id],
            'type' => 'all',
            'message' => 'Mohon periksa antrean prioritas.',
            'current_password' => 'password',
        ])->assertRedirect();

        $this->assertDatabaseHas('permit_notifications', ['user_id' => $tr->id, 'permit_id' => $permit->id, 'type' => 'manual_queue_reminder']);
        $this->assertDatabaseMissing('permit_notifications', ['user_id' => $mep->id, 'permit_id' => $permit->id]);
        $this->assertDatabaseMissing('permit_notifications', ['user_id' => $admin->id, 'permit_id' => $permit->id]);
    }

    public function test_admin_emergency_validation_requires_admin_and_records_the_reason(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081200008004']);
        $permit = $this->loadingPermit();

        $this->actingAs($tenant)->post(route('admin.emergency-validation.loading', $permit), [
            'decision' => 'rejected',
            'review_notes' => 'Dokumen belum dapat diterima.',
            'emergency_reason' => 'Validator sedang tidak tersedia.',
            'current_password' => 'password',
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('admin.emergency-validation.loading', $permit), [
            'decision' => 'rejected',
            'review_notes' => 'Dokumen belum dapat diterima.',
            'emergency_reason' => 'Validator sedang tidak tersedia.',
            'current_password' => 'password',
        ])->assertRedirect();

        $this->assertSame('rejected', $permit->fresh()->status);
        $this->assertTrue(AuditLog::query()->where('event', 'emergency_validation')->where('auditable_id', $permit->id)->exists());
    }

    public function test_admin_can_handle_each_actionable_work_permit_stage(): void
    {
        Notification::fake();
        Event::fake([WorkPermitWorkflowUpdated::class]);
        $admin = $this->admin();

        $cases = [
            ['tr_review', 'TR', 'rejected', 'rejected', []],
            ['ep_review', 'EP', 'rejected', 'rejected', []],
            ['mep_review', 'MEP', 'require_deposit', 'awaiting_payment', ['deposit_amount' => 1500000]],
            ['payment_review', 'MEP', 'verified', 'mep_final_review', ['deposit_required' => true, 'deposit_amount' => 1500000]],
            ['mep_final_review', 'MEP', 'rejected', 'rejected', ['deposit_required' => true, 'deposit_amount' => 1500000]],
        ];

        foreach ($cases as [$status, $division, $decision, $expected, $extra]) {
            $permit = $this->workPermit([...$extra, 'status' => $status, 'assigned_division' => $division]);
            $payload = [
                'decision' => $decision,
                'review_notes' => in_array($decision, ['rejected', 'revision'], true) ? 'Keputusan darurat dengan alasan yang jelas.' : null,
                'emergency_reason' => 'Validator divisi sedang tidak tersedia.',
                'current_password' => 'password',
            ];
            if ($decision === 'require_deposit') {
                $payload['deposit_amount'] = 1500000;
            }

            $this->actingAs($admin)->post(route('admin.emergency-validation.work', $permit), $payload)->assertRedirect();
            $this->assertSame($expected, $permit->fresh()->status);
        }
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true, 'phone' => '081200008001']);
    }

    private function loadingPermit(): LoadingPermit
    {
        return LoadingPermit::query()->create([
            'permit_number' => 'MBG/LOAD/X/TEST-'.uniqid(),
            'tenant_name' => 'Tenant Uji',
            'applicant_name' => 'Pemohon Uji',
            'applicant_phone' => '081234567890',
            'direction' => 'in',
            'movement_time' => '22:00',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'item_count' => 2,
            'item_unit' => 'koli',
            'status' => 'pending',
        ]);
    }

    private function workPermit(array $overrides = []): WorkPermit
    {
        return WorkPermit::query()->create(array_merge([
            'contractor_name' => 'Kontraktor Uji',
            'applicant_name' => 'Pemohon Uji',
            'applicant_phone' => '081234567890',
            'work_location' => 'Unit GF-01',
            'work_category' => 'other',
            'assigned_division' => 'MEP',
            'work_type' => 'Perbaikan instalasi',
            'work_schedule' => 'inside_store',
            'work_schedules' => ['inside_store'],
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'needs_water' => false,
            'security_deposit' => false,
            'id_doc_type' => 'ktp',
            'status' => 'mep_review',
        ], $overrides));
    }
}
