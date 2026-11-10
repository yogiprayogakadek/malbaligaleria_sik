<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProductionDataResetEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('app.production_data_reset_enabled'), 404);

        return $next($request);
    }
}
