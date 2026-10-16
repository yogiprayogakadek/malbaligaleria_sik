<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureStaffTwoFactor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '192.168.0.252');
        $middleware->prepend(AssignRequestId::class);
        $middleware->web(append: [EnsurePasswordChanged::class, EnsureStaffTwoFactor::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->context(function (): array {
            if (! app()->bound('request')) {
                return [];
            }

            return array_filter([
                'request_id' => app('request')->attributes->get('request_id'),
            ]);
        });

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($exception instanceof QueryException && $response->getStatusCode() >= 500) {
                $headers = [
                    'Cache-Control' => 'no-store, max-age=0',
                    'Retry-After' => '60',
                ];

                $response = $request->expectsJson()
                    ? response()->json([
                        'message' => 'Layanan sementara tidak tersedia. Silakan coba kembali beberapa saat lagi.',
                    ], 503, $headers)
                    : response()->view('errors.503', [], 503, $headers);
            }

            if ($requestId = $request->attributes->get('request_id')) {
                $response->headers->set('X-Request-ID', (string) $requestId);
            }

            return $response;
        });
    })->create();
