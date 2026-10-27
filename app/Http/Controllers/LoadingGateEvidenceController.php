<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\SecurityGateEvent;
use App\Models\SecurityOfficer;
use App\Services\SecurityGateEvidenceResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LoadingGateEvidenceController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'event' => ['nullable', Rule::in(['check_in', 'check_out'])],
            'q' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $query = SecurityGateEvent::query()
            ->where('permit_type', 'loading')
            ->with(['securityOfficer:id,name,officer_id', 'loadingPermit:id,permit_number,tenant_name']);

        if ($event = $validated['event'] ?? null) {
            $query->where('event', $event);
        }
        if ($from = $validated['date_from'] ?? null) {
            $query->whereDate('occurred_at', '>=', $from);
        }
        if ($to = $validated['date_to'] ?? null) {
            $query->whereDate('occurred_at', '<=', $to);
        }
        if ($search = trim((string) ($validated['q'] ?? ''))) {
            $permitIds = LoadingPermit::query()
                ->where(fn ($permits) => $permits
                    ->where('permit_number', 'like', '%'.$search.'%')
                    ->orWhere('tenant_name', 'like', '%'.$search.'%'))
                ->limit(500)
                ->pluck('id');
            $officerIds = SecurityOfficer::query()
                ->where(fn ($officers) => $officers
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('officer_id', 'like', '%'.$search.'%'))
                ->limit(500)
                ->pluck('id');

            $query->where(fn ($events) => $events
                ->whereIn('permit_id', $permitIds)
                ->orWhereIn('security_officer_id', $officerIds));
        }

        $summaryQuery = clone $query;
        $events = $query->latest('occurred_at')->latest('id')->paginate(20)->withQueryString();
        $isAdmin = $request->user()->isAdmin();

        return view('loading-evidence.index', [
            'events' => $events,
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'today' => (clone $summaryQuery)->where('occurred_at', '>=', now()->startOfDay())->count(),
                'check_in' => (clone $summaryQuery)->where('event', 'check_in')->count(),
                'check_out' => (clone $summaryQuery)->where('event', 'check_out')->count(),
            ],
            'filters' => [
                'event' => $validated['event'] ?? '',
                'q' => $validated['q'] ?? '',
                'date_from' => $validated['date_from'] ?? '',
                'date_to' => $validated['date_to'] ?? '',
            ],
            'layout' => $isAdmin ? 'layouts.admin' : 'layouts.validator',
            'routePrefix' => $isAdmin ? 'admin.loading-evidence' : 'tr.loading-evidence',
            'permitRoute' => $isAdmin ? 'admin.loading.show' : 'tr.show',
            'backRoute' => $isAdmin ? 'admin.loading.index' : 'tr.index',
        ]);
    }

    public function evidence(SecurityGateEvent $securityGateEvent, SecurityGateEvidenceResponse $evidence): BinaryFileResponse
    {
        return $evidence->make($securityGateEvent);
    }
}
