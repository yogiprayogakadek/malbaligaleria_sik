<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(18));
        $request->attributes->set('csp_nonce', $nonce);

        $response = $next($request);

        if (! config('http_security.enabled')) {
            return $response;
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if (config('http_security.csp.enabled')) {
            $response->headers->set(
                config('http_security.csp.report_only') ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy',
                $this->contentSecurityPolicy($nonce),
            );
        }

        if (app()->environment('production') && $request->isSecure() && config('http_security.hsts.enabled')) {
            $response->headers->set('Strict-Transport-Security', $this->hstsValue());
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        $connectSources = ["'self'"];
        if ($reverbSource = $this->reverbWebSocketSource()) {
            $connectSources[] = $reverbSource;
        }

        $directives = [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
            'frame-ancestors' => ["'none'"],
            'form-action' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'"],
            'script-src-attr' => ["'none'"],
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],
            'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com'],
            'img-src' => ["'self'", 'data:', 'blob:', 'https://*.tile.openstreetmap.org'],
            'connect-src' => array_unique($connectSources),
            'worker-src' => ["'self'", 'blob:'],
            'manifest-src' => ["'self'"],
            'media-src' => ["'self'", 'blob:'],
        ];

        if (! app()->environment('production')) {
            array_push($directives['script-src'], 'http://localhost:*', 'http://127.0.0.1:*');
            array_push($directives['connect-src'], 'http://localhost:*', 'http://127.0.0.1:*', 'ws://localhost:*', 'ws://127.0.0.1:*');
        }

        $policy = collect($directives)
            ->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', $sources))
            ->implode('; ');

        return Str::finish($policy, ';');
    }

    private function reverbWebSocketSource(): ?string
    {
        $options = (array) config('reverb.apps.apps.0.options', []);
        $host = strtolower((string) ($options['host'] ?? ''));
        if (! preg_match('/^[a-z0-9.-]+$/', $host)) {
            return null;
        }

        $scheme = ($options['scheme'] ?? 'https') === 'https' ? 'wss' : 'ws';
        $port = filter_var($options['port'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);

        return $scheme.'://'.$host.($port ? ':'.$port : '');
    }

    private function hstsValue(): string
    {
        $value = 'max-age='.max(0, (int) config('http_security.hsts.max_age'));

        if (config('http_security.hsts.include_subdomains')) {
            $value .= '; includeSubDomains';
        }

        if (config('http_security.hsts.preload')) {
            $value .= '; preload';
        }

        return $value;
    }
}
