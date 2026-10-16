<?php

namespace Tests\Feature;

use App\Services\DeploymentPreflightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
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

    public function test_csp_uses_a_per_request_nonce_and_blocks_inline_event_handlers(): void
    {
        $first = $this->get(route('login'))->assertOk();
        $second = $this->get(route('login'))->assertOk();

        $firstPolicy = (string) $first->headers->get('Content-Security-Policy');
        $secondPolicy = (string) $second->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/script-src [^;]*'nonce-[^']+'/", $firstPolicy);
        $this->assertStringContainsString("script-src-attr 'none'", $firstPolicy);
        $this->assertStringContainsString("frame-ancestors 'none'", $firstPolicy);
        $this->assertNotSame($firstPolicy, $secondPolicy);

        preg_match("/'nonce-([^']+)'/", $firstPolicy, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $this->assertStringContainsString('nonce="'.($matches[1] ?? '').'"', $first->getContent());
    }

    public function test_blade_inline_scripts_have_nonces_and_no_inline_event_handlers_remain(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            $contents = File::get($file->getPathname());
            preg_match_all('/<script(?![^>]*\bsrc=)([^>]*)>/i', $contents, $scripts);

            foreach ($scripts[0] as $script) {
                $this->assertStringContainsString('nonce=', $script, $file->getRelativePathname());
            }

            $this->assertDoesNotMatchRegularExpression(
                '/\son(?:click|change|submit|input|load|error)\s*=/i',
                $contents,
                $file->getRelativePathname(),
            );
        }
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
            'http_security.csp.enabled' => false,
            'http_security.csp.report_only' => true,
            'http_security.trusted_hosts' => ['other.example.test'],
        ]);

        $result = collect(app(DeploymentPreflightService::class)->run())
            ->firstWhere('name', 'Konfigurasi aplikasi');

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('HTTP_SECURITY_HEADERS_ENABLED', $result['detail']);
        $this->assertStringContainsString('HTTP_HSTS_ENABLED', $result['detail']);
        $this->assertStringContainsString('HTTP_CSP_ENABLED', $result['detail']);
        $this->assertStringContainsString('HTTP_CSP_REPORT_ONLY', $result['detail']);
        $this->assertStringContainsString('APP_TRUSTED_HOSTS', $result['detail']);
    }
}
