<?php

namespace Tests\Feature;

use App\Models\ScannerSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminScannerSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_manage_scanner_access(): void
    {
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'phone' => '081200000071',
        ]);

        $this->get(route('admin.settings.scanner.edit'))->assertRedirect(route('login'));
        $this->actingAs($tenant)->get(route('admin.settings.scanner.edit'))->assertForbidden();
    }

    public function test_admin_can_restrict_scanner_to_a_coordinate_and_radius(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.settings.scanner.edit'))
            ->assertOk()
            ->assertSee('Scanner tetap dapat dibuka tanpa login')
            ->assertSee('Area tertentu')
            ->assertSee('data-scanner-map', false);

        $this->actingAs($admin)->put(route('admin.settings.scanner.update'), [
            'access_mode' => 'geofence',
            'latitude' => -8.7212345,
            'longitude' => 115.1845678,
            'radius_meters' => 250,
        ])->assertRedirect(route('admin.settings.scanner.edit'));

        $this->assertDatabaseHas('scanner_settings', [
            'id' => 1,
            'access_mode' => 'geofence',
            'latitude' => -8.7212345,
            'longitude' => 115.1845678,
            'radius_meters' => 250,
        ]);
    }

    public function test_geofence_values_are_validated_by_the_backend(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.settings.scanner.edit'))
            ->put(route('admin.settings.scanner.update'), [
                'access_mode' => 'geofence',
                'latitude' => -91,
                'longitude' => 181,
                'radius_meters' => 10,
            ])
            ->assertRedirect(route('admin.settings.scanner.edit'))
            ->assertSessionHasErrors(['latitude', 'longitude', 'radius_meters']);
    }

    public function test_anywhere_mode_removes_stored_coordinates(): void
    {
        ScannerSetting::query()->findOrFail(1)->update([
            'access_mode' => 'geofence',
            'latitude' => -8.7212345,
            'longitude' => 115.1845678,
            'radius_meters' => 250,
        ]);

        $this->actingAs($this->admin())->put(route('admin.settings.scanner.update'), [
            'access_mode' => 'anywhere',
        ])->assertRedirect(route('admin.settings.scanner.edit'));

        $setting = ScannerSetting::query()->findOrFail(1);
        $this->assertSame('anywhere', $setting->access_mode);
        $this->assertNull($setting->latitude);
        $this->assertNull($setting->longitude);
        $this->assertNull($setting->radius_meters);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'phone' => fake()->unique()->numerify('08##########'),
        ]);
    }
}
