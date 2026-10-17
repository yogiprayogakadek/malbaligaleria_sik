<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\ScannerVerificationLog;
use App\Models\WorkPermit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminScannerLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $results = ['active', 'revoked', 'expired', 'not_approved', 'not_found', 'invalid', 'integrity_error', 'location_required', 'location_inaccurate', 'location_denied'];
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
        ]);
    }
}
