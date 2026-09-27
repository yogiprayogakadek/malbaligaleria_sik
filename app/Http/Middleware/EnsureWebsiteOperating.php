<?php

namespace App\Http\Middleware;

use App\Models\OperatingSchedule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebsiteOperating
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (($user?->is_active && ($user->isAdmin() || $user->isValidator())) || OperatingSchedule::siteIsOpen()) {
            return $next($request);
        }

        $nextOpening = OperatingSchedule::nextOpening();
        $response = response()->view('closed', [
            'schedules' => OperatingSchedule::weekly(),
            'nextOpening' => $nextOpening,
        ], 503);

        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        if ($nextOpening) {
            $response->headers->set('Retry-After', (string) max(60, now()->diffInSeconds($nextOpening, false)));
        }

        return $response;
    }
}
