<?php

namespace Tests\Feature;

use App\Models\OperatingSchedule;
use App\Models\OperatingScheduleException;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatingHoursTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_portal_returns_secure_503_page_outside_operating_hours(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-28 20:00', 'Asia/Makassar'));
        $this->setMondaySchedule('08:00', '17:00');

        $response = $this->get(route('portal.dashboard'));

        $response->assertStatus(503)->assertSee('Portal sedang tidak beroperasi');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('Retry-After');
    }

    public function test_staff_routes_remain_available_when_public_portal_is_closed(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-28 20:00', 'Asia/Makassar'));
        $this->setMondaySchedule('08:00', '17:00');
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200001111']);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('portal.dashboard'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_overnight_schedule_is_open_after_midnight_from_previous_day(): void
    {
        $this->setMondaySchedule('22:00', '02:00');

        $this->assertTrue(OperatingSchedule::siteIsOpen(CarbonImmutable::parse('2026-09-28 23:30', 'Asia/Makassar')));
        $this->assertTrue(OperatingSchedule::siteIsOpen(CarbonImmutable::parse('2026-09-29 01:30', 'Asia/Makassar')));
        $this->assertFalse(OperatingSchedule::siteIsOpen(CarbonImmutable::parse('2026-09-29 03:00', 'Asia/Makassar')));
    }

    public function test_admin_can_update_complete_weekly_schedule(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200001112']);
        $days = [];

        foreach (range(0, 6) as $day) {
            $days[$day] = [
                'day_of_week' => $day,
                'is_open' => $day !== 0 ? 1 : 0,
                'is_24_hours' => 0,
                'opens_at' => '09:00',
                'closes_at' => '18:00',
            ];
        }

        $this->actingAs($admin)
            ->get(route('admin.schedule.edit'))
            ->assertOk()
            ->assertSee('Loading/Unloading dan Surat Izin Kerja')
            ->assertSee('Surat Izin Event');

        $this->actingAs($admin)
            ->put(route('admin.schedule.update'), ['scope' => OperatingSchedule::SCOPE_GENERAL, 'days' => $days])
            ->assertRedirect(route('admin.schedule.edit'));

        $this->assertDatabaseHas('operating_schedules', ['day_of_week' => 0, 'is_open' => false]);
        $this->assertDatabaseHas('operating_schedules', ['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '18:00']);
    }

    public function test_date_exception_overrides_the_weekly_schedule(): void
    {
        $this->setMondaySchedule('08:00', '17:00');
        OperatingScheduleException::query()->create([
            'date' => '2026-09-28',
            'label' => 'Maintenance',
            'is_open' => false,
            'is_24_hours' => false,
        ]);

        $this->assertFalse(OperatingSchedule::siteIsOpen(CarbonImmutable::parse('2026-09-28 10:00', 'Asia/Makassar')));

        OperatingScheduleException::query()->whereDate('date', '2026-09-28')->update([
            'is_open' => true,
            'opens_at' => '10:00',
            'closes_at' => '12:00',
        ]);

        $this->assertTrue(OperatingSchedule::siteIsOpen(CarbonImmutable::parse('2026-09-28 11:00', 'Asia/Makassar')));
        $this->assertFalse(OperatingSchedule::siteIsOpen(CarbonImmutable::parse('2026-09-28 13:00', 'Asia/Makassar')));
    }

    public function test_admin_can_add_and_remove_a_future_schedule_exception(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-28 08:00', 'Asia/Makassar'));
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200001113']);

        $this->actingAs($admin)->post(route('admin.schedule.exceptions.store'), [
            'scope' => OperatingSchedule::SCOPE_GENERAL,
            'date' => '2026-10-01',
            'label' => 'Maintenance jaringan',
            'is_open' => '0',
            'is_24_hours' => '0',
        ])->assertRedirect(route('admin.schedule.edit'));

        $exception = OperatingScheduleException::query()->firstOrFail();
        $this->assertFalse($exception->is_open);

        $this->actingAs($admin)
            ->delete(route('admin.schedule.exceptions.destroy', $exception))
            ->assertRedirect(route('admin.schedule.edit'));

        $this->assertDatabaseMissing('operating_schedule_exceptions', ['id' => $exception->id]);
    }

    public function test_event_schedule_can_be_changed_without_affecting_other_permits(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-28 20:00', 'Asia/Makassar'));
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200001114']);
        $days = [];

        foreach (range(0, 6) as $day) {
            $days[$day] = [
                'day_of_week' => $day,
                'is_open' => $day === 1 ? 1 : 0,
                'is_24_hours' => 0,
                'opens_at' => '08:00',
                'closes_at' => '17:00',
            ];
        }

        $this->actingAs($admin)
            ->put(route('admin.schedule.update'), ['scope' => OperatingSchedule::SCOPE_EVENT, 'days' => $days])
            ->assertRedirect(route('admin.schedule.edit'));
        auth()->logout();

        $this->assertFalse(OperatingSchedule::siteIsOpen(scope: OperatingSchedule::SCOPE_EVENT));
        $this->assertTrue(OperatingSchedule::siteIsOpen(scope: OperatingSchedule::SCOPE_GENERAL));
        $this->get(route('event-permits.create'))
            ->assertStatus(503)
            ->assertSee('Pengajuan Surat Izin Event sedang tidak beroperasi');
        $this->get(route('loading.create'))->assertOk();
        $this->get(route('work-permits.create'))->assertOk();
    }

    public function test_event_and_general_schedules_can_have_exceptions_on_the_same_date(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-28 08:00', 'Asia/Makassar'));
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200001115']);

        foreach ([OperatingSchedule::SCOPE_GENERAL, OperatingSchedule::SCOPE_EVENT] as $scope) {
            $this->actingAs($admin)->post(route('admin.schedule.exceptions.store'), [
                'scope' => $scope,
                'date' => '2026-10-01',
                'label' => $scope === OperatingSchedule::SCOPE_EVENT ? 'Event tutup' : 'Layanan umum tutup',
                'is_open' => '0',
                'is_24_hours' => '0',
            ])->assertRedirect(route('admin.schedule.edit'));
        }

        $this->assertDatabaseCount('operating_schedule_exceptions', 2);
        $this->assertSame(1, OperatingScheduleException::query()->where('scope', 'general')->whereDate('date', '2026-10-01')->count());
        $this->assertSame(1, OperatingScheduleException::query()->where('scope', 'event')->whereDate('date', '2026-10-01')->count());
    }

    private function setMondaySchedule(string $opensAt, string $closesAt): void
    {
        OperatingSchedule::where('day_of_week', 1)->update([
            'is_open' => true,
            'is_24_hours' => false,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
        ]);
        OperatingSchedule::where('day_of_week', '!=', 1)->update([
            'is_open' => false,
            'is_24_hours' => false,
            'opens_at' => null,
            'closes_at' => null,
        ]);
    }
}
