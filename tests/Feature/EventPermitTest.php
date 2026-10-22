<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureWebsiteOperating;
use App\Http\Middleware\RequireSensitiveActionConfirmation;
use App\Models\EventRequirement;
use App\Models\PermitNotification;
use App\Models\User;
use App\Models\WorkPermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EventPermitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureWebsiteOperating::class);
        Notification::fake();
        Queue::fake();
    }

    public function test_guest_can_submit_an_event_permit_for_ep_with_requirement_snapshot(): void
    {
        $ep = User::factory()->create(['role' => 'validator', 'division' => 'EP', 'phone' => '081200000101']);
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000102']);
        $secretary = User::factory()->create(['role' => 'secretary', 'phone' => '081200000103']);
        $requirement = EventRequirement::query()->where('name', 'Kelistrikan')->firstOrFail();

        $token = $this->get(route('event-permits.create'))
            ->assertOk()
            ->viewData('submissionToken');

        $response = $this->post(route('event-permits.store'), $this->payload($token, [
            'requirement_ids' => [$requirement->id],
            'includes_loading_material' => '1',
        ]));

        $permit = WorkPermit::query()->where('permit_kind', 'event')->sole();
        $response->assertRedirect();
        $this->assertSame('EP', $permit->assigned_division);
        $this->assertSame('ep_review', $permit->status);
        $this->assertSame('Kelistrikan', $permit->event_requirements[0]['name']);
        $this->assertTrue($permit->includes_loading_material);
        $this->assertStringStartsWith('MBG/EVENT/', $permit->permit_number);
        $this->assertSame(3, PermitNotification::query()->where('work_permit_id', $permit->id)->count());
        $this->assertDatabaseHas('permit_notifications', ['user_id' => $ep->id, 'title' => 'Permohonan Surat Izin Event Baru']);
        $this->assertDatabaseHas('permit_notifications', ['user_id' => $admin->id]);
        $this->assertDatabaseHas('permit_notifications', ['user_id' => $secretary->id]);
    }

    public function test_event_permit_rejects_a_period_longer_than_seven_days(): void
    {
        $token = $this->get(route('event-permits.create'))->viewData('submissionToken');

        $this->from(route('event-permits.create'))
            ->post(route('event-permits.store'), $this->payload($token, ['end_date' => now()->addDays(7)->toDateString()]))
            ->assertRedirect(route('event-permits.create'))
            ->assertSessionHasErrors('end_date');

        $this->assertDatabaseCount('work_permits', 0);
    }

    public function test_ep_can_approve_event_and_generate_an_official_document(): void
    {
        $ep = User::factory()->create(['role' => 'validator', 'division' => 'EP', 'phone' => '081200000104']);
        $token = $this->get(route('event-permits.create'))->viewData('submissionToken');
        $this->post(route('event-permits.store'), $this->payload($token));
        $permit = WorkPermit::query()->where('permit_kind', 'event')->sole();

        $this->actingAs($ep)
            ->post(route('ep.work-permits.decision', $permit->public_token), [
                'decision' => 'approved',
                'review_notes' => 'Kegiatan disetujui.',
            ])
            ->assertRedirect(route('ep.index'));

        $permit->refresh();
        $this->assertSame('approved', $permit->status);
        $this->assertNotNull($permit->official_document_id);
        $this->assertNotNull($permit->official_document_sha256);
    }

    public function test_admin_can_manage_event_requirements(): void
    {
        $this->withoutMiddleware(RequireSensitiveActionConfirmation::class);
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000105']);

        $this->actingAs($admin)->post(route('admin.event-requirements.store'), [
            'group_name' => 'Peralatan',
            'name' => 'Panggung modular',
            'sort_order' => 120,
            'is_active' => 1,
        ])->assertRedirect();

        $item = EventRequirement::query()->where('name', 'Panggung modular')->sole();
        $this->actingAs($admin)->put(route('admin.event-requirements.update', $item), [
            'group_name' => 'Peralatan',
            'name' => 'Panggung event',
            'sort_order' => 121,
            'is_active' => 0,
        ])->assertRedirect();

        $this->assertDatabaseHas('event_requirements', ['id' => $item->id, 'name' => 'Panggung event', 'is_active' => false]);
    }

    private function payload(string $token, array $overrides = []): array
    {
        return array_merge([
            'submission_token' => $token,
            'event_type' => 'organizer',
            'organizer_name' => 'EO Bali Kreatif',
            'applicant_name' => 'Made Event',
            'applicant_phone' => '081234567890',
            'applicant_email' => 'event@example.com',
            'work_location' => 'Atrium Mal Bali Galeria',
            'work_category' => 'mall_event',
            'work_type' => 'Pemasangan panggung dan pelaksanaan acara',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'notes' => 'Pemasangan panggung, tata suara, dan pelaksanaan acara selama masa izin.',
            'includes_loading_material' => '0',
            'requirement_ids' => [],
            'terms_accepted' => '1',
        ], $overrides);
    }
}
