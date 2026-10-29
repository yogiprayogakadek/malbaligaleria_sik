<?php

namespace App\Http\Controllers;

use App\Models\OperationTrace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminOperationTraceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'operation' => ['nullable', 'string', 'in:loading_submission,work_permit_submission,event_permit_submission'],
            'status' => ['nullable', 'string', 'in:success,failed'],
        ]);

        $query = OperationTrace::query()
            ->with(['loadingPermit:id,permit_number', 'workPermit:id,permit_number'])
            ->when($filters['operation'] ?? null, fn (Builder $builder, string $operation) => $builder->where('operation', $operation))
            ->when($filters['status'] ?? null, fn (Builder $builder, string $status) => $builder->where('status', $status));

        $recent = OperationTrace::query()->where('started_at', '>=', now()->subDay());
        $durations = (clone $recent)->orderBy('total_ms')->pluck('total_ms');
        $p95Index = $durations->isEmpty() ? null : (int) ceil($durations->count() * 0.95) - 1;

        return view('admin.operation-traces.index', [
            'traces' => $query->latest('started_at')->paginate(30)->withQueryString(),
            'filters' => $filters,
            'metrics' => [
                'count' => $durations->count(),
                'average_ms' => (int) round((clone $recent)->avg('total_ms') ?? 0),
                'p95_ms' => $p95Index === null ? 0 : (int) $durations->get($p95Index),
                'failed' => (clone $recent)->where('status', 'failed')->count(),
            ],
            'operationLabels' => $this->operationLabels(),
        ]);
    }

    public function show(OperationTrace $trace): View
    {
        $trace->loadMissing([
            'loadingPermit:id,permit_number',
            'workPermit:id,permit_number',
            'actor:id,name,role,division',
        ]);

        return view('admin.operation-traces.show', [
            'trace' => $trace,
            'operationLabels' => $this->operationLabels(),
        ]);
    }

    /** @return array<string, string> */
    private function operationLabels(): array
    {
        return [
            'loading_submission' => 'Pengajuan Loading/Unloading',
            'work_permit_submission' => 'Pengajuan Surat Izin Kerja',
            'event_permit_submission' => 'Pengajuan Surat Izin Event',
            'permit_submission' => 'Pengajuan Permohonan',
        ];
    }
}
