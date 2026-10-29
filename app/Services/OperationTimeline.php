<?php

namespace App\Services;

use App\Models\LoadingPermit;
use App\Models\OperationTrace;
use App\Models\WorkPermit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;
use Throwable;

class OperationTimeline
{
    private bool $active = false;

    private int $startedNs = 0;

    private string $requestId = '';

    private string $operation = '';

    private ?string $routeName = null;

    private ?int $actorId = null;

    private int $uploadCount = 0;

    private int $uploadBytes = 0;

    private int $queryCount = 0;

    private float $queryMs = 0;

    private ?int $loadingPermitId = null;

    private ?int $workPermitId = null;

    /** @var array<int, array{name: string, duration_ms: float, status: string, error?: string}> */
    private array $steps = [];

    public function start(
        string $requestId,
        string $operation,
        ?string $routeName,
        ?int $actorId,
        int $uploadCount,
        int $uploadBytes,
    ): void {
        $this->active = true;
        $this->startedNs = hrtime(true);
        $this->requestId = $requestId;
        $this->operation = $operation;
        $this->routeName = $routeName;
        $this->actorId = $actorId;
        $this->uploadCount = $uploadCount;
        $this->uploadBytes = $uploadBytes;
        $this->queryCount = 0;
        $this->queryMs = 0;
        $this->loadingPermitId = null;
        $this->workPermitId = null;
        $this->steps = [];
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    public function measure(string $name, callable $callback): mixed
    {
        if (! $this->active) {
            return $callback();
        }

        $startedNs = hrtime(true);

        try {
            $result = $callback();
            $this->steps[] = [
                'name' => $name,
                'duration_ms' => $this->elapsedMs($startedNs),
                'status' => 'success',
            ];

            return $result;
        } catch (Throwable $exception) {
            $this->steps[] = [
                'name' => $name,
                'duration_ms' => $this->elapsedMs($startedNs),
                'status' => 'failed',
                'error' => class_basename($exception),
            ];

            throw $exception;
        }
    }

    public function recordQuery(QueryExecuted $query): void
    {
        if (! $this->active) {
            return;
        }

        $this->queryCount++;
        $this->queryMs += (float) $query->time;
    }

    public function associatePermit(LoadingPermit|WorkPermit $permit): void
    {
        if ($permit instanceof LoadingPermit) {
            $this->loadingPermitId = $permit->id;

            return;
        }

        $this->workPermitId = $permit->id;
    }

    public function finish(string $status, ?int $responseStatus = null, ?string $error = null): ?OperationTrace
    {
        if (! $this->active) {
            return null;
        }

        $totalMs = max(0, (int) round((hrtime(true) - $this->startedNs) / 1_000_000));
        $measuredMs = array_sum(array_column($this->steps, 'duration_ms'));
        $overheadMs = max(0, round($totalMs - $measuredMs, 2));

        if ($overheadMs >= 0.01) {
            array_unshift($this->steps, [
                'name' => 'Validasi dan overhead request',
                'duration_ms' => $overheadMs,
                'status' => 'success',
            ]);
        }

        if ($status === 'failed' && ! collect($this->steps)->contains('status', 'failed')) {
            $this->steps[] = [
                'name' => 'Proses dihentikan',
                'duration_ms' => 0,
                'status' => 'failed',
                'error' => $error ?: 'HTTP '.$responseStatus,
            ];
        }

        $slowest = collect($this->steps)->sortByDesc('duration_ms')->first();
        $startedAt = now()->subMilliseconds($totalMs);
        $this->active = false;

        try {
            return OperationTrace::query()->create([
                'request_id' => $this->requestId,
                'operation' => $this->operation,
                'route_name' => $this->routeName,
                'status' => $status,
                'response_status' => $responseStatus,
                'total_ms' => $totalMs,
                'query_count' => $this->queryCount,
                'query_ms' => max(0, (int) round($this->queryMs)),
                'upload_count' => $this->uploadCount,
                'upload_bytes' => $this->uploadBytes,
                'slowest_step' => $slowest['name'] ?? null,
                'slowest_step_ms' => max(0, (int) round($slowest['duration_ms'] ?? 0)),
                'steps' => $this->steps,
                'loading_permit_id' => $this->loadingPermitId,
                'work_permit_id' => $this->workPermitId,
                'actor_id' => $this->actorId,
                'started_at' => $startedAt,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Timeline operasi tidak dapat disimpan.', [
                'request_id' => $this->requestId,
                'operation' => $this->operation,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function elapsedMs(int $startedNs): float
    {
        return round((hrtime(true) - $startedNs) / 1_000_000, 2);
    }
}
