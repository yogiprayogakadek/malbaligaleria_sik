<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureWebsiteOperating;
use App\Http\Middleware\RequireSensitiveActionConfirmation;
use App\Jobs\SendApprovedEventWhatsappGroup;
use App\Models\EventRequirement;
use App\Models\EventWhatsappGroupDelivery;
use App\Models\EventWhatsappGroupMember;
use App\Models\EventWhatsappGroupRule;
use App\Models\EventWhatsappGroupSetting;
use App\Models\User;
use App\Models\WhatsappSetting;
use App\Models\WorkPermit;
use App\Services\PermitPdfService;
use App\Services\WahaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EventWhatsappGroupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureWebsiteOperating::class);
        Notification::fake();
        Queue::fake();
    }

    public function test_admin_can_configure_group_members_and_conditional_rules(): void
    {
        $this->withoutMiddleware(RequireSensitiveActionConfirmation::class);
        WhatsappSetting::query()->create($this->whatsappSetting());
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081288800101']);

        $this->actingAs($admin)->put(route('admin.event-whatsapp-group.update'), [
            'is_active' => '1',
            'group_name' => 'Koordinasi Event MBG',
            'group_id' => '120363000000000001@g.us',
            'base_message' => "Surat {{permit_number}} disetujui.\n{{conditional_messages}}\n{{mentions}}",
        ])->assertRedirect();

        $this->post(route('admin.event-whatsapp-group.members.store'), [
            'name' => 'Koordinator Engineering',
            'phone' => '081234567891',
            'mention_by_default' => '0',
            'is_active' => '1',
        ])->assertRedirect();

        $member = EventWhatsappGroupMember::query()->sole();
        $requirement = EventRequirement::query()->where('name', 'Kelistrikan')->firstOrFail();
        $this->post(route('admin.event-whatsapp-group.rules.store'), [
            'name' => 'Koordinasi kelistrikan',
            'requirement_group' => 'Fasilitas',
            'event_requirement_id' => $requirement->id,
            'message' => 'Mohon periksa kebutuhan daya Event.',
            'priority' => 10,
            'is_active' => '1',
            'member_ids' => [$member->id],
        ])->assertRedirect();

        $this->get(route('admin.event-whatsapp-group.index'))
            ->assertOk()
            ->assertSee('Trigger tunggal: surat disetujui')
            ->assertSee('Koordinasi kelistrikan')
            ->assertSee('Koordinator Engineering');
        $this->assertTrue(EventWhatsappGroupSetting::current()->is_active);
        $this->assertSame([$member->id], EventWhatsappGroupRule::query()->sole()->members->pluck('id')->all());
    }

    public function test_only_approved_event_queues_one_group_message_and_official_pdf(): void
    {
        Http::fake([
            'http://192.168.0.253:3000/api/sendText' => Http::response(['id' => ['id' => 'group-text-1']]),
            'http://192.168.0.253:3000/api/sendFile' => Http::response(['id' => ['id' => 'group-file-1']]),
        ]);
        WhatsappSetting::query()->create($this->whatsappSetting());
        $group = $this->groupSetting();
        $member = EventWhatsappGroupMember::query()->create([
            'name' => 'Koordinator Engineering',
            'phone' => '6281234567891',
            'phone_hash' => hash('sha256', '6281234567891'),
            'mention_by_default' => false,
            'is_active' => true,
        ]);
        $requirement = EventRequirement::query()->where('name', 'Kelistrikan')->firstOrFail();
        $rule = EventWhatsappGroupRule::query()->create([
            'name' => 'Koordinasi kelistrikan',
            'requirement_group' => 'Fasilitas',
            'event_requirement_id' => $requirement->id,
            'message' => 'Mohon periksa kebutuhan daya Event.',
            'priority' => 10,
            'is_active' => true,
        ]);
        $rule->members()->attach($member);

        $permit = $this->submitEvent([$requirement->id]);
        $this->assertDatabaseCount('event_whatsapp_group_deliveries', 0);

        $ep = User::factory()->create(['role' => 'validator', 'division' => 'EP', 'phone' => '081288800102']);
        $this->actingAs($ep)->post(route('ep.work-permits.decision', $permit->public_token), [
            'decision' => 'approved',
            'review_notes' => 'Kegiatan disetujui.',
        ])->assertRedirect(route('ep.index'));

        $delivery = EventWhatsappGroupDelivery::query()->sole();
        $this->assertSame($group->id, $delivery->setting_id);
        $this->assertStringContainsString('Mohon periksa kebutuhan daya Event.', $delivery->message);
        $this->assertStringContainsString('@6281234567891', $delivery->message);
        $this->assertSame(['6281234567891@c.us'], $delivery->mentions);
        $this->assertSame('Surat Izin Event - EO Bali Kreatif.pdf', $delivery->filename);
        Queue::assertPushed(SendApprovedEventWhatsappGroup::class, 1);

        (new SendApprovedEventWhatsappGroup($delivery->id))->handle(app(WahaClient::class), app(PermitPdfService::class));

        $delivery->refresh();
        $this->assertSame('sent', $delivery->text_status);
        $this->assertSame('sent', $delivery->file_status);
        $this->assertSame('group-text-1', $delivery->text_provider_message_id);
        $this->assertSame('group-file-1', $delivery->file_provider_message_id);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://192.168.0.253:3000/api/sendText'
            && $request['chatId'] === '120363000000000001@g.us'
            && $request['mentions'] === ['6281234567891@c.us']);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://192.168.0.253:3000/api/sendFile'
            && $request['file']['filename'] === 'Surat Izin Event - EO Bali Kreatif.pdf'
            && $request['file']['mimetype'] === 'application/pdf'
            && filled($request['file']['data']));
    }

    public function test_rejected_event_does_not_queue_group_delivery(): void
    {
        WhatsappSetting::query()->create($this->whatsappSetting());
        $this->groupSetting();
        $permit = $this->submitEvent();
        $ep = User::factory()->create(['role' => 'validator', 'division' => 'EP', 'phone' => '081288800103']);

        $this->actingAs($ep)->post(route('ep.work-permits.decision', $permit->public_token), [
            'decision' => 'rejected',
            'review_notes' => 'Jadwal belum dapat disetujui.',
        ])->assertRedirect(route('ep.index'));

        $this->assertDatabaseCount('event_whatsapp_group_deliveries', 0);
        Queue::assertNotPushed(SendApprovedEventWhatsappGroup::class);
    }

    /** @param array<int, int> $requirementIds */
    private function submitEvent(array $requirementIds = []): WorkPermit
    {
        $token = $this->get(route('event-permits.create'))->viewData('submissionToken');
        $this->post(route('event-permits.store'), [
            'submission_token' => $token,
            'event_type' => 'organizer',
            'organizer_name' => 'EO Bali Kreatif',
            'applicant_name' => 'Made Event',
            'applicant_phone' => '081234567890',
            'applicant_email' => 'event@example.com',
            'work_location' => 'Atrium Mal Bali Galeria',
            'work_category' => 'mall_event',
            'work_type' => 'Pelaksanaan acara dan pemasangan panggung',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'notes' => 'Pemasangan panggung, tata suara, dan pelaksanaan acara selama masa izin.',
            'includes_loading_material' => '0',
            'requirement_ids' => $requirementIds,
            'terms_accepted' => '1',
        ])->assertRedirect();

        return WorkPermit::query()->where('permit_kind', 'event')->latest('id')->firstOrFail();
    }

    private function groupSetting(): EventWhatsappGroupSetting
    {
        return EventWhatsappGroupSetting::query()->create([
            'is_active' => true,
            'group_name' => 'Koordinasi Event MBG',
            'group_id' => '120363000000000001@g.us',
            'group_id_hash' => hash('sha256', '120363000000000001@g.us'),
            'base_message' => EventWhatsappGroupSetting::DEFAULT_MESSAGE,
        ]);
    }

    private function whatsappSetting(): array
    {
        return [
            'base_url' => 'http://192.168.0.253:3000',
            'api_key' => 'waha-private-api-key-123456',
            'session' => 'default',
            'timeout' => 10,
            'is_active' => true,
            'notify_applicants' => true,
        ];
    }
}
