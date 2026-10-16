<?php

namespace Tests\Feature;

use App\Services\DeploymentPreflightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentPreflightTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_command_succeeds_when_required_services_are_available(): void
    {
        $this->artisan('app:preflight')
            ->expectsOutputToContain('Preflight lulus')
            ->assertSuccessful();
    }

    public function test_production_preflight_rejects_unsafe_application_and_queue_configuration(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'app.debug' => true,
            'app.url' => 'http://sik.example.test',
            'session.secure' => false,
            'queue.default' => 'sync',
        ]);

        $results = collect(app(DeploymentPreflightService::class)->run());

        $this->assertSame('error', $results->firstWhere('name', 'Konfigurasi aplikasi')['status']);
        $this->assertSame('error', $results->firstWhere('name', 'Queue')['status']);
        $this->assertStringContainsString('APP_DEBUG harus false', $results->firstWhere('name', 'Konfigurasi aplikasi')['detail']);
        $this->assertStringContainsString('HTTPS', $results->firstWhere('name', 'Konfigurasi aplikasi')['detail']);
    }
}
