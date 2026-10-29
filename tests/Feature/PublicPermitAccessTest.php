<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPermitAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_active_permit_forms_are_accessible_without_login(): void
    {
        $this->get(route('loading.create'))->assertOk();
        $this->get(route('work-permits.create'))->assertOk();
        $this->get(route('event-permits.create'))->assertOk();

        foreach (['loading.store', 'work-permits.store', 'event-permits.store'] as $routeName) {
            $this->assertNotContains('auth', app('router')->getRoutes()->getByName($routeName)->gatherMiddleware());
        }
    }

    public function test_public_permit_forms_do_not_trigger_staff_password_or_two_factor_gates(): void
    {
        $staff = User::factory()->create([
            'phone' => '081234567890',
            'role' => 'admin',
            'division' => null,
            'is_active' => true,
            'must_change_password' => true,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $this->actingAsWithoutTwoFactor($staff);

        $this->get(route('loading.create'))->assertOk();
        $this->get(route('work-permits.create'))->assertOk();
        $this->get(route('event-permits.create'))->assertOk();
    }
}
