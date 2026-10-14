<?php

namespace App\Http\Controllers;

use App\Models\SystemAlert;
use App\Services\SystemHealthService;
use Illuminate\View\View;

class AdminSystemHealthController extends Controller
{
    public function __invoke(SystemHealthService $health): View
    {
        $checks = collect($health->checks());

        return view('admin.health.index', [
            'checks' => $checks,
            'healthyCount' => $checks->where('status', 'healthy')->count(),
            'warningCount' => $checks->where('status', 'warning')->count(),
            'errorCount' => $checks->where('status', 'error')->count(),
            'checkedAt' => now(),
            'systemAlerts' => SystemAlert::query()->latest('last_detected_at')->limit(10)->get(),
        ]);
    }
}
