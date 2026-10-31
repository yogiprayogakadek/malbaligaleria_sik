<?php

namespace Tests\Feature;

use App\Events\FormPresenceUpdated;
use App\Models\FormPresence;
use App\Models\User;
use App\Services\FormPresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FormPresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_browser_is_counted_once_when_it_opens_multiple_tabs(): void
    {
        Event::fake([FormPresenceUpdated::class]);
        $visitor = 'a320f073-93a9-4189-b332-9bb43f38e7f2';

        $this->postJson(route('form-presence.heartbeat', 'loading'), [
            'tab_id' => '1d5ccf14-591c-43dd-8a49-43fa85dba9e1',
            'visitor_id' => $visitor,
        ])->assertOk()->assertJsonPath('counts.loading', 1);

        $this->postJson(route('form-presence.heartbeat', 'loading'), [
            'tab_id' => 'fe8d35bd-5f18-498d-97b6-58d41cf0b6b8',
            'visitor_id' => $visitor,
        ])->assertOk()->assertJsonPath('counts.loading', 1);

        $this->assertDatabaseCount('form_presences', 2);
        $this->assertSame(1, app(FormPresenceService::class)->snapshot()['total']);
        $this->assertDatabaseMissing('form_presences', ['visitor_hash' => $visitor]);
    }

    public function test_leaving_the_last_tab_removes_browser_from_active_count(): void
    {
        Event::fake([FormPresenceUpdated::class]);
        $firstTab = '1d5ccf14-591c-43dd-8a49-43fa85dba9e1';
        $secondTab = 'fe8d35bd-5f18-498d-97b6-58d41cf0b6b8';
        $visitor = 'a320f073-93a9-4189-b332-9bb43f38e7f2';

        $this->postJson(route('form-presence.heartbeat', 'work'), ['tab_id' => $firstTab, 'visitor_id' => $visitor]);
        $this->postJson(route('form-presence.heartbeat', 'work'), ['tab_id' => $secondTab, 'visitor_id' => $visitor]);
        $this->postJson(route('form-presence.leave', 'work'), ['tab_id' => $firstTab, 'visitor_id' => $visitor])
            ->assertOk()
            ->assertJsonPath('counts.work', 1);
        $this->postJson(route('form-presence.leave', 'work'), ['tab_id' => $secondTab, 'visitor_id' => $visitor])
            ->assertOk()
            ->assertJsonPath('counts.work', 0);
    }

    public function test_expired_presence_is_excluded_and_pruned(): void
    {
        Event::fake([FormPresenceUpdated::class]);
        FormPresence::query()->create([
            'form_type' => 'event',
            'visitor_hash' => str_repeat('a', 64),
            'tab_hash' => str_repeat('b', 64),
            'last_seen_at' => now()->subMinutes(2),
            'expires_at' => now()->subSecond(),
        ]);

        $this->assertSame(0, app(FormPresenceService::class)->snapshot()['counts']['event']);
        $this->assertSame(1, app(FormPresenceService::class)->prune());
        $this->assertDatabaseCount('form_presences', 0);
    }

    public function test_activity_page_and_snapshot_are_admin_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000001', 'is_active' => true]);
        $validator = User::factory()->create(['role' => 'validator', 'division' => 'TR', 'phone' => '081200000002', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.form-presence.index'))
            ->assertOk()
            ->assertSee('Pengunjung yang sedang mengisi form')
            ->assertSee(route('admin.form-presence.snapshot'), false);
        $this->actingAs($admin)
            ->getJson(route('admin.form-presence.snapshot'))
            ->assertOk()
            ->assertJsonStructure(['counts' => ['loading', 'work', 'event'], 'total', 'updated_at']);
        $this->actingAs($validator)->get(route('admin.form-presence.index'))->assertForbidden();
    }

    public function test_all_public_permit_forms_include_presence_tracker(): void
    {
        $this->get(route('loading.create'))->assertOk()->assertSee('data-form-type="loading"', false);
        $this->get(route('work-permits.create'))->assertOk()->assertSee('data-form-type="work"', false);
        $this->get(route('event-permits.create'))->assertOk()->assertSee('data-form-type="event"', false);
    }
}
