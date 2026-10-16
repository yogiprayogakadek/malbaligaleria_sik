<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
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

        if (app()->environment('production') && $request->isSecure() && config('http_security.hsts.enabled')) {
            $response->headers->set('Strict-Transport-Security', $this->hstsValue());
        }

        return $response;
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
