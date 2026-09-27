<?php

namespace Tests\Feature;

use App\Models\OperatingSchedule;
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
            ->put(route('admin.schedule.update'), ['days' => $days])
            ->assertRedirect(route('admin.schedule.edit'));

        $this->assertDatabaseHas('operating_schedules', ['day_of_week' => 0, 'is_open' => false]);
        $this->assertDatabaseHas('operating_schedules', ['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '18:00']);
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
