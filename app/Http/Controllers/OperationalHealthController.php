<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OperationalHealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $expected = (string) config('monitoring.health_token');
        abort_unless(strlen($expected) >= 32 && hash_equals($expected, (string) $request->bearerToken()), 404);

        $scheduler = $this->fresh('system-health:scheduler');
        $queue = config('queue.default') === 'sync' || $this->fresh('system-health:queue-worker');
        $sla = $this->fresh('system-health:permit-sla-monitor', 8);
        $healthy = $scheduler && $queue && $sla;

        return response()->json([
            'status' => $healthy ? 'ok' : 'error',
            'scheduler' => $scheduler ? 'ok' : 'stale',
            'queue' => $queue ? 'ok' : 'stale',
            'sla_monitor' => $sla ? 'ok' : 'stale',
            'checked_at' => now()->toIso8601String(),
        ], $healthy ? 200 : 503)->header('Cache-Control', 'no-store');
    }

    private function fresh(string $key, int $minutes = 3): bool
    {
        $heartbeat = Cache::get($key);

        return $heartbeat && CarbonImmutable::parse($heartbeat)->greaterThan(now()->subMinutes($minutes));
    }
}
