<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class RequestIdTest extends TestCase
{
    public function test_every_http_response_receives_a_generated_request_id(): void
    {
        $response = $this->get('/up')->assertOk();
        $requestId = $response->headers->get('X-Request-ID');

        $this->assertIsString($requestId);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $requestId);
    }

    public function test_error_page_and_header_expose_the_same_safe_reference(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/request-id-error', fn () => throw new RuntimeException('Internal detail must stay hidden.'));

        $response = $this->withExceptionHandling()->get('/_test/request-id-error')->assertInternalServerError();
        $requestId = $response->headers->get('X-Request-ID');

        $this->assertIsString($requestId);
        $response
            ->assertSee($requestId)
            ->assertSee('Referensi bantuan')
            ->assertDontSee('Internal detail must stay hidden.');
    }

    public function test_method_not_allowed_uses_the_shared_recovery_page(): void
    {
        config(['app.debug' => false]);
        Route::post('/_test/post-only', fn () => response('ok'));

        $this->withExceptionHandling()->get('/_test/post-only')
            ->assertMethodNotAllowed()
            ->assertSee('Cara membuka halaman tidak didukung')
            ->assertSee('Ke beranda');
    }

    public function test_unhandled_client_errors_use_the_shared_fallback(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/client-error', fn () => abort(418));

        $this->withExceptionHandling()->get('/_test/client-error')
            ->assertStatus(418)
            ->assertSee('Permintaan tidak dapat diproses');
    }

    public function test_unhandled_server_errors_use_the_shared_fallback(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/server-error', fn () => abort(502));

        $this->withExceptionHandling()->get('/_test/server-error')
            ->assertStatus(502)
            ->assertSee('Layanan mengalami gangguan');
    }

    public function test_non_get_error_returns_to_the_previous_page_instead_of_replaying_as_get(): void
    {
        config(['app.debug' => false]);
        Route::post('/_test/limited', fn () => abort(429));

        $this->withExceptionHandling()
            ->from('/admin/data-maintenance')
            ->post('/_test/limited')
            ->assertTooManyRequests()
            ->assertSee('Kembali')
            ->assertSee(url('/admin/data-maintenance'), false)
            ->assertDontSee('href="'.url('/_test/limited').'"', false);
    }
}
