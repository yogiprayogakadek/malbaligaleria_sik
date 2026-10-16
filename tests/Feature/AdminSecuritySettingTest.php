<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SecuritySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSecuritySettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_manage_staff_two_factor_policy(): void
    {
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'phone' => '081200000081',
        ]);

        $this->get(route('admin.settings.security.edit'))->assertRedirect(route('login'));
        $this->actingAs($tenant)->get(route('admin.settings.security.edit'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.settings.security.edit'))
            ->assertOk()
            ->assertSee('Keamanan login staf')
            ->assertSee('Wajib untuk seluruh staf')
            ->assertSee('Peringatan aktivitas login');
    }

    public function test_admin_password_is_required_to_change_the_policy(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.settings.security.update'), [
            'staff_two_factor_required' => '0',
            'login_alerts_enabled' => '1',
            'failed_login_alert_threshold' => '3',
            'current_password' => 'salah-password',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(SecuritySetting::current()->staff_two_factor_required);
    }

    public function test_admin_can_disable_and_reenable_staff_two_factor_requirement(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.settings.security.update'), [
            'staff_two_factor_required' => '0',
            'login_alerts_enabled' => '1',
            'failed_login_alert_threshold' => '4',
            'current_password' => 'password',
        ])->assertRedirect(route('admin.settings.security.edit'));

        $this->assertFalse(SecuritySetting::current()->staff_two_factor_required);
        $this->assertTrue(SecuritySetting::current()->login_alerts_enabled);
        $this->assertSame(4, SecuritySetting::current()->failed_login_alert_threshold);

        $this->actingAs($admin)->put(route('admin.settings.security.update'), [
            'staff_two_factor_required' => '1',
            'login_alerts_enabled' => '0',
            'failed_login_alert_threshold' => '5',
            'current_password' => 'password',
        ])->assertRedirect(route('admin.settings.security.edit'));

        $this->assertTrue(SecuritySetting::current()->staff_two_factor_required);
        $this->assertFalse(SecuritySetting::current()->login_alerts_enabled);
        $this->assertSame(5, SecuritySetting::current()->failed_login_alert_threshold);
        $this->assertSame(2, AuditLog::query()
            ->where('auditable_type', SecuritySetting::class)
            ->where('auditable_id', 1)
            ->where('event', 'updated')
            ->count());
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'phone' => fake()->unique()->numerify('08##########'),
        ]);
    }
}
