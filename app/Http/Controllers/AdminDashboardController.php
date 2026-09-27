<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\OperatingSchedule;
use App\Models\User;

class AdminDashboardController extends Controller
{
    public function __invoke()
    {
        $counts = [
            'loading' => LoadingPermit::count(),
            'pending' => LoadingPermit::where('status', 'pending')->count(),
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
