<?php

namespace App\Http\Controllers;

use App\Models\EmailMessageLog;
use App\Models\LoadingPermit;
use App\Models\OperatingSchedule;
use App\Models\SystemAlert;
use App\Models\User;
use App\Models\WhatsappMessageLog;
use App\Models\WorkPermit;

class AdminDashboardController extends Controller
{
    public function __invoke()
    {
        $pending = [
            'loading' => LoadingPermit::where('status', 'pending')->count(),
            'work' => WorkPermit::where('permit_kind', 'work')->where('status', 'pending')->count(),
            'event' => WorkPermit::where('permit_kind', 'event')->where('status', 'pending')->count(),
        ];
        $failedCommunications = EmailMessageLog::where('status', 'failed')->count()
            + WhatsappMessageLog::where('status', 'failed')->count();
        $unresolvedAlerts = SystemAlert::whereNull('resolved_at')->count();

        $counts = [
            'loading' => LoadingPermit::count(),
            'pending' => array_sum($pending),
            'pending_by_type' => $pending,
            'failed_communications' => $failedCommunications,
            'unresolved_alerts' => $unresolvedAlerts,
            'action_required' => array_sum($pending) + $failedCommunications + $unresolvedAlerts,
            'validators' => User::where('role', 'validator')->count(),
            'active_validators' => User::where('role', 'validator')->where('is_active', true)->count(),
        ];
        $recentPermits = LoadingPermit::latest()->take(6)->get();

        return view('admin.dashboard', [
            'counts' => $counts,
            'recentPermits' => $recentPermits,
            'siteIsOpen' => OperatingSchedule::siteIsOpen(),
            'nextOpening' => OperatingSchedule::nextOpening(),
        ]);
    }
}
