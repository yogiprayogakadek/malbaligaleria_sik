<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\SecurityGateEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminSecurityReconciliationController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        $dateFrom = Carbon::parse($validated['date_from'] ?? today()->subDays(30)->toDateString())->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'] ?? today()->addDays(7)->toDateString())->endOfDay();
        if ($dateFrom->diffInDays($dateTo) > 90) {
            throw ValidationException::withMessages([
                'date_to' => 'Rentang rekonsiliasi maksimal 90 hari.',
            ]);
        }

        $unscannedQuery = LoadingPermit::query()
            ->where('status', 'approved')
            ->whereNull('revoked_at')
            ->whereDate('start_date', '<=', $dateTo->toDateString())
            ->whereDate('end_date', '>=', $dateFrom->toDateString())
            ->where(function ($query): void {
                $query->whereDate('start_date', '<', today())
                    ->orWhere(function ($today): void {
                        $today->whereDate('start_date', today())
                            ->whereTime('movement_time', '<=', now()->format('H:i:s'));
                    });
            })
            ->whereDoesntHave('scannerVerifications', fn ($query) => $query->where('result', 'active'));

        $latestGateEventIds = SecurityGateEvent::query()
            ->selectRaw('MAX(id)')
            ->where('permit_type', 'loading')
            ->groupBy('permit_id');
        $openAccessQuery = SecurityGateEvent::query()
            ->where('permit_type', 'loading')
            ->where('event', 'check_in')
            ->whereIn('id', $latestGateEventIds);

        $outsideWindowQuery = SecurityGateEvent::query()
            ->select('security_gate_events.*')
            ->join('loading_permits', 'loading_permits.id', '=', 'security_gate_events.permit_id')
            ->where('security_gate_events.permit_type', 'loading')
            ->whereBetween('security_gate_events.occurred_at', [$dateFrom, $dateTo])
            ->where(function ($query): void {
                $query->whereRaw('DATE(security_gate_events.occurred_at) < DATE(loading_permits.start_date)')
                    ->orWhereRaw('DATE(security_gate_events.occurred_at) > DATE(loading_permits.end_date)')
                    ->orWhereRaw("TIME(security_gate_events.occurred_at) > '11:00:00' AND TIME(security_gate_events.occurred_at) < '22:00:00'");
            });

        return view('admin.security-reconciliation.index', [
            'unscannedPermits' => (clone $unscannedQuery)->latest('end_date')->limit(25)->get(),
            'openAccessEvents' => (clone $openAccessQuery)
                ->with(['loadingPermit:id,permit_number,tenant_name,start_date,end_date,movement_time', 'securityOfficer:id,name,officer_id'])
                ->oldest('occurred_at')
                ->limit(25)
                ->get(),
            'outsideWindowEvents' => (clone $outsideWindowQuery)
                ->with(['loadingPermit:id,permit_number,tenant_name,start_date,end_date,movement_time', 'securityOfficer:id,name,officer_id'])
                ->latest('security_gate_events.occurred_at')
                ->limit(25)
                ->get(),
            'summary' => [
                'unscanned' => (clone $unscannedQuery)->count(),
                'open_access' => (clone $openAccessQuery)->count(),
                'outside_window' => (clone $outsideWindowQuery)->count(),
            ],
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
        ]);
    }
}
