<?php

namespace App\Http\Middleware;

use App\Models\UploadSecuritySetting;
use App\Services\OperationTimeline;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MonitorPermitSubmission
{
    public function __construct(private OperationTimeline $timeline) {}

    public function handle(Request $request, Closure $next): Response
    {
        $files = collect($request->allFiles())->flatten();
        $this->timeline->start(
            (string) $request->attributes->get('request_id'),
            match ($request->route()?->getName()) {
                'loading.store' => 'loading_submission',
                'work-permits.store' => 'work_permit_submission',
                'event-permits.store' => 'event_permit_submission',
                default => 'permit_submission',
            },
            $request->route()?->getName(),
            $request->user()?->id,
            $files->count(),
            $files->sum(fn ($file): int => (int) $file->getSize()),
        );

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->timeline->finish('failed', null, class_basename($exception));

            throw $exception;
        }

        $responseException = property_exists($response, 'exception') ? $response->exception : null;
        $failed = $response->getStatusCode() >= 400 || $responseException instanceof Throwable;
        $trace = $this->timeline->finish(
            $failed ? 'failed' : 'success',
            $response->getStatusCode(),
            $responseException instanceof Throwable ? class_basename($responseException) : null,
        );
        $thresholdMs = (int) config('permit-submissions.slow_request_ms', 3000);

        if (! $trace || $trace->total_ms < $thresholdMs) {
            return $response;
        }

        Log::warning('Pengajuan permohonan diproses lebih lambat dari batas pemantauan.', [
            'trace_id' => $trace->id,
            'route' => $request->route()?->getName(),
            'duration_ms' => $trace->total_ms,
            'slowest_step' => $trace->slowest_step,
            'slowest_step_ms' => $trace->slowest_step_ms,
            'query_count' => $trace->query_count,
            'query_ms' => $trace->query_ms,
            'upload_count' => $trace->upload_count,
            'upload_bytes' => $trace->upload_bytes,
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
