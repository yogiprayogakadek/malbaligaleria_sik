<?php

namespace Tests\Feature;

use App\Models\PermitSubmissionLimitSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminPermitSubmissionLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_manage_permit_submission_limits(): void
    {
        $tenant = $this->user('tenant');
        $admin = $this->user('admin');

        $this->get(route('admin.settings.permit-limits.edit'))->assertRedirect(route('login'));
        $this->actingAs($tenant)->get(route('admin.settings.permit-limits.edit'))->assertForbidden();

        $this->actingAs($admin)->put(route('admin.settings.permit-limits.update'), [
            'enabled' => '1',
            'max_per_minute' => 20,
            'max_per_day' => 200,
        ])->assertRedirect(route('admin.settings.permit-limits.edit'));

        $this->assertDatabaseHas('permit_submission_limit_settings', [
            'id' => 1,
            'enabled' => true,
            'max_per_minute' => 20,
            'max_per_day' => 200,
        ]);

        $this->get(route('admin.health.index'))
            ->assertOk()
            ->assertSee(route('admin.settings.permit-limits.edit'), false);
    }

    public function test_submission_limiter_uses_admin_configuration(): void
    {
        PermitSubmissionLimitSetting::query()->findOrFail(1)->update([
            'enabled' => true,
            'max_per_minute' => 2,
            'max_per_day' => 100,
        ]);
        $tenant = $this->user('tenant');
        $this->clearTenantLimits($tenant);

        $this->actingAs($tenant)->post(route('work-permits.store'), [])->assertSessionDoesntHaveErrors('submission');
        $this->post(route('work-permits.store'), [])->assertSessionDoesntHaveErrors('submission');
        $this->post(route('work-permits.store'), [])->assertSessionHasErrors('submission');
    }

    public function test_submission_limiter_can_be_disabled_for_testing(): void
    {
        PermitSubmissionLimitSetting::query()->findOrFail(1)->update([
            'enabled' => false,
            'max_per_minute' => 1,
            'max_per_day' => 1,
        ]);
        $tenant = $this->user('tenant');
        $this->clearTenantLimits($tenant);

        $this->actingAs($tenant)->post(route('work-permits.store'), [])->assertSessionDoesntHaveErrors('submission');
        $this->post(route('work-permits.store'), [])->assertSessionDoesntHaveErrors('submission');
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'phone' => fake()->unique()->numerify('08##########'),
            'is_active' => true,
        ]);
    }

    private function clearTenantLimits(User $tenant): void
    {
        $prefix = "permit-submission:tenant:{$tenant->id}";
        RateLimiter::clear("{$prefix}:minute");
        RateLimiter::clear("{$prefix}:day");
    }
}
