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
}
