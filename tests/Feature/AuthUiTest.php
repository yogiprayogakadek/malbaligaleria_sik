<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_uses_the_same_blue_auth_tone_as_login(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('auth-page--register', false)
            ->assertSee('<use href="#i-phone"/>', false)
            ->assertSee("input.type === 'checkbox'", false)
            ->assertSee('? input.checked', false);

        $styles = file_get_contents(public_path('styles.css'));

        $this->assertStringContainsString('.auth-page--register .auth-screen', $styles);
        $this->assertStringContainsString('.auth-page--register .btn-auth', $styles);
        $this->assertStringContainsString('.auth-page--register .auth-tab-indicator', $styles);
    }
}
