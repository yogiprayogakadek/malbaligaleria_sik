<?php

namespace App\Http\Middleware;

use App\Models\UploadSecuritySetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MonitorPermitSubmission
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $response = $next($request);
        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        $thresholdMs = (int) config('permit-submissions.slow_request_ms', 3000);

        if ($durationMs < $thresholdMs) {
            return $response;
        }

        $files = collect($request->allFiles())->flatten();

        Log::warning('Pengajuan permohonan diproses lebih lambat dari batas pemantauan.', [
            'route' => $request->route()?->getName(),
            'duration_ms' => $durationMs,
            'upload_count' => $files->count(),
            'upload_bytes' => $files->sum(fn ($file): int => (int) $file->getSize()),
            'malware_scanning_enabled' => rescue(
                fn (): bool => UploadSecuritySetting::scanningEnabled(),
                'unknown',
                false,
            ),
            'queue_connection' => config('queue.default'),
            'response_status' => $response->getStatusCode(),
        ]);

        return $response;
    }
}
