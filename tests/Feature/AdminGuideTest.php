<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_and_download_the_system_guide(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'division' => null,
            'phone' => '081277770001',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.guide.index'))
            ->assertOk()
            ->assertSee('Alur kerja dan fungsi menu')
            ->assertSee('Generate panduan PDF')
            ->assertSee('Panduan sistem');

        $this->actingAs($admin)
            ->get(route('admin.guide.download'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_non_admin_cannot_access_the_system_guide(): void
    {
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'division' => null,
            'phone' => '081277770002',
        ]);

        $this->actingAs($tenant)->get(route('admin.guide.index'))->assertForbidden();
    }
}
