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
}
