<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\ScannerIpBlock;
use App\Models\ScannerVerificationLog;
use App\Models\SecurityGateEvent;
use App\Models\WorkPermit;
use App\Services\SecurityGateEvidenceResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminScannerLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $results = ['active', 'revoked', 'expired', 'not_approved', 'not_found', 'invalid', 'integrity_error', 'location_required', 'location_inaccurate', 'location_denied', 'blocked'];
        $riskResults = ['revoked', 'not_found', 'invalid', 'integrity_error', 'location_denied'];
        $validated = $request->validate([
            'result' => ['nullable', Rule::in($results)],
            'type' => ['nullable', Rule::in(['loading', 'work'])],
        ]);

        $logs = ScannerVerificationLog::query()
            ->with('user:id,name')
            ->when($validated['result'] ?? null, fn ($query, $result) => $query->where('result', $result))
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('permit_type', $type))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $loadingReferences = LoadingPermit::query()
            ->whereIn('id', $logs->getCollection()->where('permit_type', 'loading')->pluck('permit_id')->filter())
            ->pluck('permit_number', 'id');
        $workReferences = WorkPermit::query()
            ->whereIn('id', $logs->getCollection()->where('permit_type', 'work')->pluck('permit_id')->filter())
            ->pluck('permit_number', 'id');

        $riskWindow = now()->subDay();
        $recentRiskLogs = ScannerVerificationLog::query()
            ->where('created_at', '>=', $riskWindow)
            ->whereIn('result', $riskResults)
            ->latest('id')
            ->limit(2000)
            ->get();
        $repeatedSources = $recentRiskLogs
            ->filter(fn (ScannerVerificationLog $log): bool => filled($log->ip_hash))
            ->groupBy('ip_hash')
            ->map(fn ($items): array => [
                'ip_address' => $items->first()->ip_address,
                'attempts' => $items->count(),
                'last_seen_at' => $items->first()->created_at,
                'results' => $items->pluck('result')->countBy()->all(),
            ])
            ->filter(fn (array $source): bool => $source['attempts'] >= 5)
            ->sortByDesc('attempts')
            ->take(10)
            ->values();

        return view('admin.scanner-logs.index', [
            'logs' => $logs,
            'result' => $validated['result'] ?? '',
            'type' => $validated['type'] ?? '',
            'results' => $results,
            'loadingReferences' => $loadingReferences,
            'workReferences' => $workReferences,
            'totalLogs' => ScannerVerificationLog::count(),
            'todayLogs' => ScannerVerificationLog::where('created_at', '>=', now()->startOfDay())->count(),
            'failedToday' => ScannerVerificationLog::where('created_at', '>=', now()->startOfDay())->where('result', '!=', 'active')->count(),
            'riskEventsToday' => ScannerVerificationLog::where('created_at', '>=', now()->startOfDay())->whereIn('result', $riskResults)->count(),
            'integrityErrorsToday' => ScannerVerificationLog::where('created_at', '>=', now()->startOfDay())->where('result', 'integrity_error')->count(),
            'repeatedSources' => $repeatedSources,
            'activeBlocks' => ScannerIpBlock::query()
                ->whereNull('unblocked_at')
                ->where('blocked_until', '>', now())
                ->latest('updated_at')
                ->get(),
            'gateEvents' => SecurityGateEvent::query()
                ->with('securityOfficer:id,name,officer_id')
                ->latest('id')
                ->limit(20)
                ->get(),
        ]);
    }

    public function evidence(SecurityGateEvent $securityGateEvent, SecurityGateEvidenceResponse $evidence): BinaryFileResponse
    {
        return $evidence->make($securityGateEvent);
    }
}
