<?php

namespace Tests\Feature;

use App\Models\SecuritySetting;
use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_without_two_factor_is_forced_to_setup_but_tenant_is_not(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081288800001']);
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081288800002']);

        $this->actingAsWithoutTwoFactor($admin)->get(route('admin.dashboard'))
            ->assertRedirect(route('two-factor.setup.show'));

        Auth::logout();
        $this->actingAsWithoutTwoFactor($tenant)->get(route('portal.dashboard'))->assertOk();
    }

    public function test_staff_two_factor_checks_are_bypassed_when_admin_disables_the_policy(): void
    {
        SecuritySetting::query()->findOrFail(1)->update(['staff_two_factor_required' => false]);
        $adminWithoutTwoFactor = User::factory()->create([
            'role' => 'admin',
            'phone' => '081288800011',
        ]);

        $this->actingAsWithoutTwoFactor($adminWithoutTwoFactor)
            ->get(route('admin.dashboard'))
            ->assertOk();

        Auth::logout();
        $secret = app(TwoFactorAuthenticationService::class)->generateSecret();
        $configuredAdmin = User::factory()->create([
            'role' => 'admin',
            'phone' => '081288800012',
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post(route('login.post'), [
            'login' => $configuredAdmin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($configuredAdmin);
    }

    public function test_staff_can_confirm_setup_and_receives_one_time_recovery_codes(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081288800003']);
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $secret = $twoFactor->generateSecret();
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $response = $this->actingAsWithoutTwoFactor($admin)
            ->withSession(['two_factor.setup_secret' => $secret])
            ->post(route('two-factor.setup.confirm'), ['code' => $code]);

        $response->assertRedirect(route('two-factor.setup.show'))
            ->assertSessionHas('two_factor.new_recovery_codes', fn (array $codes): bool => count($codes) === 8);

        $admin->refresh();
        $this->assertTrue($admin->hasConfirmedTwoFactor());
        $this->assertCount(8, $admin->two_factor_recovery_codes);
        $this->assertNotSame($secret, $admin->getRawOriginal('two_factor_secret'));
        $this->assertNotNull(session('two_factor.verified_at'));
    }

    public function test_confirmed_staff_login_requires_a_valid_totp_before_authentication(): void
    {
        $secret = app(TwoFactorAuthenticationService::class)->generateSecret();
        $admin = User::factory()->create([
            'role' => 'admin',
            'phone' => '081288800004',
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post(route('login.post'), ['login' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge.show'));
        $this->assertGuest();

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $this->post(route('two-factor.challenge.verify'), ['code' => $code])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull(session('two_factor.verified_at'));
    }

    public function test_recovery_code_can_only_be_used_once(): void
    {
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $secret = $twoFactor->generateSecret();
        $recoveryCode = 'ABCDE-23456';
        $admin = User::factory()->create([
            'role' => 'admin',
            'phone' => '081288800005',
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes([$recoveryCode]),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->withSession(['two_factor.user_id' => $admin->id])
            ->post(route('two-factor.challenge.verify'), ['code' => $recoveryCode])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertCount(0, $admin->refresh()->two_factor_recovery_codes);

        Auth::logout();
        $this->withSession(['two_factor.user_id' => $admin->id])
            ->post(route('two-factor.challenge.verify'), ['code' => $recoveryCode])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_totp_code_cannot_be_replayed(): void
    {
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $secret = $twoFactor->generateSecret();
        $admin = User::factory()->create([
            'role' => 'admin',
            'phone' => '081288800009',
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);
        $otp = app(Google2FA::class)->getCurrentOtp($secret);

        $this->assertTrue($twoFactor->verifyForUser($admin, $otp));
        $this->assertFalse($twoFactor->verifyForUser($admin, $otp));
    }

    public function test_two_factor_challenge_is_rate_limited(): void
    {
        $secret = app(TwoFactorAuthenticationService::class)->generateSecret();
        $admin = User::factory()->create([
            'role' => 'admin',
            'phone' => '081288800006',
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);

        foreach (range(1, 6) as $attempt) {
            $this->withSession(['two_factor.user_id' => $admin->id])
                ->post(route('two-factor.challenge.verify'), ['code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        $this->withSession(['two_factor.user_id' => $admin->id])
            ->post(route('two-factor.challenge.verify'), ['code' => '000000'])
            ->assertTooManyRequests();
    }

    public function test_regenerating_recovery_codes_requires_password_and_totp_and_invalidates_old_codes(): void
    {
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $secret = $twoFactor->generateSecret();
        $oldCode = 'ABCDE-23456';
        $admin = User::factory()->create([
            'role' => 'admin',
            'phone' => '081288800008',
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes([$oldCode]),
            'two_factor_confirmed_at' => now(),
        ]);
        $otp = app(Google2FA::class)->getCurrentOtp($secret);

        $this->actingAs($admin)->post(route('two-factor.setup.recovery-codes.regenerate'), [
            'current_password' => 'password',
            'code' => $otp,
        ])->assertRedirect(route('two-factor.setup.show'))
            ->assertSessionHas('two_factor.new_recovery_codes');

        $admin->refresh();
        $this->assertCount(8, $admin->two_factor_recovery_codes);
        $this->assertFalse($twoFactor->consumeRecoveryCode($admin, $oldCode));
    }

    public function test_two_factor_setup_page_contains_a_local_qr_code(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081288800007']);

        $this->actingAsWithoutTwoFactor($admin)->get(route('two-factor.setup.show'))
            ->assertOk()
            ->assertSee('Aktifkan autentikasi dua langkah')
            ->assertSee('<svg', false)
            ->assertDontSee('chart.googleapis.com');
    }

    public function test_pending_challenge_is_cancelled_when_two_factor_policy_is_disabled(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'phone' => '081288800013',
            'two_factor_secret' => app(TwoFactorAuthenticationService::class)->generateSecret(),
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);
        SecuritySetting::query()->findOrFail(1)->update(['staff_two_factor_required' => false]);

        $this->withSession([
            'two_factor.user_id' => $admin->id,
            'two_factor.remember' => true,
        ])->get(route('two-factor.challenge.show'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('two_factor.user_id')
            ->assertSessionMissing('two_factor.remember');
    }
}
