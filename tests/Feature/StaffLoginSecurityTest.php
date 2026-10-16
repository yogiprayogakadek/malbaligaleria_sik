<?php

namespace Tests\Feature;

use App\Models\SecuritySetting;
use App\Models\StaffLoginEvent;
use App\Models\TrustedLoginDevice;
use App\Models\User;
use App\Notifications\StaffLoginSecurityAlert;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffLoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_failed_staff_login_is_recorded_and_alerts_staff_and_admin_once(): void
    {
        Notification::fake();
        $admin = $this->staff('admin', null, 'admin-security@example.test');
        $validator = $this->staff('validator', 'TR', 'validator-security@example.test');

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->post(route('login.post'), [
                'login' => $validator->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('login');
        }

        $event = StaffLoginEvent::query()->sole();
        $this->assertSame(StaffLoginEvent::FAILED_ATTEMPTS, $event->event);
        $this->assertSame(3, $event->attempt_count);
        $this->assertSame($validator->id, $event->user_id);

        Notification::assertSentTo($validator, StaffLoginSecurityAlert::class);
        Notification::assertSentTo($admin, StaffLoginSecurityAlert::class);

        $this->post(route('login.post'), [
            'login' => $validator->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('login');

        $this->assertSame(1, StaffLoginEvent::query()->count());
    }

    public function test_successful_staff_login_registers_a_hashed_device_and_does_not_repeat_new_device_alert(): void
    {
        Notification::fake();
        SecuritySetting::query()->findOrFail(1)->update(['staff_two_factor_required' => false]);
        $staff = $this->staff('validator', 'TR', 'device-security@example.test');

        $response = $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0')
            ->post(route('login.post'), [
                'login' => $staff->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('tr.index'))
            ->assertCookie(config('login-security.device_cookie'));

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($item): bool => $item->getName() === config('login-security.device_cookie'));

        $this->assertNotNull($cookie);
        $deviceToken = CookieValuePrefix::remove(decrypt($cookie->getValue(), false));
        $this->assertDatabaseCount('trusted_login_devices', 1);
        $this->assertNotSame($deviceToken, TrustedLoginDevice::query()->sole()->token_hash);
        $this->assertSame(StaffLoginEvent::NEW_DEVICE, StaffLoginEvent::query()->sole()->event);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->withCookie(config('login-security.device_cookie'), $deviceToken)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0')
            ->post(route('login.post'), [
                'login' => $staff->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('tr.index'));

        $this->assertSame(1, StaffLoginEvent::query()->where('event', StaffLoginEvent::NEW_DEVICE)->count());
        $this->assertSame(1, StaffLoginEvent::query()->where('event', StaffLoginEvent::LOGIN_SUCCESS)->count());
        Notification::assertSentToTimes($staff, StaffLoginSecurityAlert::class, 1);
    }

    public function test_unknown_login_identifier_is_not_persisted(): void
    {
        Notification::fake();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->post(route('login.post'), [
                'login' => 'unknown-staff@example.test',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('login');
        }

        $this->assertDatabaseCount('staff_login_events', 0);
        Notification::assertNothingSent();
    }

    public function test_disabled_alerts_still_keep_the_security_history(): void
    {
        Notification::fake();
        SecuritySetting::query()->findOrFail(1)->update(['login_alerts_enabled' => false]);
        $staff = $this->staff('validator', 'TR', 'silent-security@example.test');

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->post(route('login.post'), [
                'login' => $staff->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('login');
        }

        $this->assertDatabaseHas('staff_login_events', [
            'user_id' => $staff->id,
            'event' => StaffLoginEvent::FAILED_ATTEMPTS,
            'attempt_count' => 3,
        ]);
        Notification::assertNothingSent();
    }

    public function test_staff_can_view_only_their_login_history(): void
    {
        $staff = $this->staff('validator', 'TR', 'history-security@example.test');
        $other = $this->staff('validator', 'MEP', 'other-history@example.test');

        StaffLoginEvent::query()->create($this->eventData($staff, 'Windows'));
        StaffLoginEvent::query()->create($this->eventData($other, 'Android'));

        $this->actingAs($staff)->get(route('account.security.index'))
            ->assertOk()
            ->assertSee('Riwayat login')
            ->assertSee('Windows')
            ->assertDontSee('Android');
    }

    private function staff(string $role, ?string $division, string $email): User
    {
        return User::factory()->create([
            'role' => $role,
            'division' => $division,
            'email' => $email,
            'phone' => fake()->unique()->numerify('08##########'),
        ]);
    }

    private function eventData(User $user, string $device): array
    {
        return [
            'user_id' => $user->id,
            'event' => StaffLoginEvent::LOGIN_SUCCESS,
            'device_label' => $device,
            'browser_label' => 'Browser uji',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test agent',
            'occurred_at' => now(),
        ];
    }
}
