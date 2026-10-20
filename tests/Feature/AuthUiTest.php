<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_login_uses_responsive_mall_photography(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('auth-layout--with-media', false)
            ->assertSee('assets/images/desktop.png', false)
            ->assertSee('assets/images/portrait.png', false)
            ->assertSee('media="(max-width: 860px)"', false);

        $styles = file_get_contents(public_path('styles.css'));

        $this->assertStringContainsString('grid-template-areas: "form media"', $styles);
        $this->assertStringContainsString('flex: 0 0 clamp(210px, 34svh, 320px)', $styles);
    }

    public function test_public_login_and_tenant_registration_routes_are_not_available(): void
    {
        $this->assertSame('/staff-access', route('login', absolute: false));
        $this->get('/login')->assertNotFound();
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }
}
