<?php

namespace Tests\Feature;

use App\Services\DeploymentPreflightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HttpSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_security_headers_are_added_without_blocking_scanner_capabilities(): void
    {
        $response = $this->get('/up');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');

        $permissions = (string) $response->headers->get('Permissions-Policy');
        $this->assertStringContainsString('camera=(self)', $permissions);
        $this->assertStringContainsString('geolocation=(self)', $permissions);
        $this->assertStringContainsString('microphone=()', $permissions);
    }

    public function test_hsts_is_only_added_to_secure_production_responses(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->get('http://localhost/up')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/up')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_security_headers_are_present_on_error_responses(): void
    {
        $this->get('/route-that-does-not-exist')
            ->assertNotFound()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_production_preflight_rejects_disabled_http_protection_or_untrusted_app_host(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'app.debug' => false,
            'app.url' => 'https://sik.example.test',
            'session.secure' => true,
            'http_security.enabled' => false,
            'http_security.hsts.enabled' => false,
            'http_security.trusted_hosts' => ['other.example.test'],
        ]);

        $result = collect(app(DeploymentPreflightService::class)->run())
            ->firstWhere('name', 'Konfigurasi aplikasi');

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('HTTP_SECURITY_HEADERS_ENABLED', $result['detail']);
        $this->assertStringContainsString('HTTP_HSTS_ENABLED', $result['detail']);
        $this->assertStringContainsString('APP_TRUSTED_HOSTS', $result['detail']);
    }
}
