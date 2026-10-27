<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_does_not_show_the_new_application_header_button(): void
    {
        $this->get(route('portal.dashboard'))
            ->assertOk()
            ->assertDontSee('Permohonan baru');
    }

    public function test_public_navigation_keeps_scanner_and_staff_login_out_of_the_main_menu(): void
    {
        $this->get(route('portal.dashboard'))
            ->assertOk()
            ->assertDontSee(route('scanner.index'), false)
            ->assertDontSee('Akses staf')
            ->assertSee('Akses internal')
            ->assertSee(route('login'), false);
    }
}
