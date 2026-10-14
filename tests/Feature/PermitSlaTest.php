<?php

namespace Tests\Feature;

use App\Events\LoadingPermitSubmitted;
use App\Events\WorkPermitWorkflowUpdated;
use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\SlaSetting;
use App\Models\User;
use App\Models\WorkPermit;
use App\Notifications\PermitSlaOverduePush;
use App\Services\PermitSlaMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PermitSlaTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_manage_sla_limits(): void
    {
        $tenant = $this->user('tenant');
        $admin = $this->user('admin');

        $this->get(route('admin.settings.sla.edit'))->assertRedirect(route('login'));
        $this->actingAs($tenant)->get(route('admin.settings.sla.edit'))->assertForbidden();

        $this->actingAs($admin)->put(route('admin.settings.sla.update'), [
            'loading_hours' => 6,
            'work_hours' => 18,
        ])->assertRedirect(route('admin.settings.sla.edit'));

        $this->assertDatabaseHas('sla_settings', [
            'id' => 1,
            'loading_hours' => 6,
            'work_hours' => 18,
        ]);
    }

    public function test_sla_clock_resets_and_stops_as_workflow_status_changes(): void
    {
        $permit = WorkPermit::query()->create($this->workPermitData());
        $initialStart = $permit->sla_started_at;

        $this->travel(2)->hours();
        $permit->update(['status' => 'awaiting_payment', 'deposit_required' => true]);
        $this->assertNull($permit->fresh()->sla_started_at);

        $this->travel(1)->hour();
        $permit->update(['status' => 'payment_review']);
        $permit->refresh();

        $this->assertNotNull($initialStart);
        $this->assertTrue($permit->sla_started_at->greaterThan($initialStart));
        $this->assertNull($permit->sla_escalated_at);
    }

    public function test_overdue_loading_is_escalated_once_to_admin_and_tr(): void
    {
        Event::fake([LoadingPermitSubmitted::class]);
        Notification::fake();
        SlaSetting::query()->findOrFail(1)->update(['loading_hours' => 2]);

        $admin = $this->user('admin');
        $tr = $this->user('validator', 'TR');
        $this->user('validator', 'MEP');
        $permit = LoadingPermit::query()->create($this->loadingPermitData());
        $permit->updateQuietly(['sla_started_at' => now()->subHours(3)]);

        $monitor = app(PermitSlaMonitor::class);
        $this->assertSame(1, $monitor->run());
        $this->assertSame(0, $monitor->run());

        $this->assertSame(2, PermitNotification::query()
            ->where('permit_id', $permit->id)
            ->where('type', 'sla_overdue')
            ->count());
        $this->assertDatabaseHas('permit_notifications', ['permit_id' => $permit->id, 'user_id' => $admin->id]);
        $this->assertDatabaseHas('permit_notifications', ['permit_id' => $permit->id, 'user_id' => $tr->id]);
        Notification::assertSentTo([$admin, $tr], PermitSlaOverduePush::class);
        Event::assertDispatchedTimes(LoadingPermitSubmitted::class, 2);
    }

    public function test_payment_review_escalation_only_targets_admin_and_finance(): void
    {
        Event::fake([WorkPermitWorkflowUpdated::class]);
        Notification::fake();
        SlaSetting::query()->findOrFail(1)->update(['work_hours' => 2]);

        $admin = $this->user('admin');
        $finance = $this->user('validator', 'FIN');
        $tr = $this->user('validator', 'TR');
        $mep = $this->user('validator', 'MEP');
        $permit = WorkPermit::query()->create(array_merge($this->workPermitData(), [
            'status' => 'payment_review',
            'deposit_required' => true,
        ]));
        $permit->updateQuietly(['sla_started_at' => now()->subHours(3)]);

        $this->assertSame(1, app(PermitSlaMonitor::class)->run());

        $recipientIds = PermitNotification::query()
            ->where('work_permit_id', $permit->id)
            ->where('type', 'sla_overdue')
            ->pluck('user_id')
            ->all();
        $this->assertEqualsCanonicalizing([$admin->id, $finance->id], $recipientIds);
        Notification::assertSentTo([$admin, $finance], PermitSlaOverduePush::class);
        Notification::assertNotSentTo([$tr, $mep], PermitSlaOverduePush::class);
    }

    private function user(string $role, ?string $division = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'division' => $division,
            'phone' => fake()->unique()->numerify('08##########'),
            'is_active' => true,
        ]);
    }

    private function loadingPermitData(): array
    {
        return [
            'permit_number' => 'MBG/SIK/SLA/0001',
            'tenant_name' => 'Tenant SLA',
            'applicant_name' => 'Pemohon SLA',
            'applicant_phone' => '081234567890',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'item_count' => 1,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian',
            'id_doc_path' => 'permits/id-docs/sla.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'pending',
        ];
    }

    private function workPermitData(): array
    {
        return [
            'contractor_name' => 'Kontraktor SLA',
            'applicant_name' => 'Pemohon SLA',
            'applicant_phone' => '081234567890',
            'work_location' => 'Unit GF-12',
            'work_category' => 'other',
            'assigned_division' => 'MEP',
            'work_type' => 'Perbaikan instalasi',
            'work_schedule' => 'inside_store',
            'work_schedules' => ['inside_store'],
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'needs_water' => false,
            'security_deposit' => false,
            'id_doc_type' => 'ktp',
            'id_doc_path' => 'permits/work/id-docs/sla.jpg',
            'status' => 'mep_review',
        ];
    }
}
