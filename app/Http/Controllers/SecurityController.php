<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\SecurityGateEvent;
use App\Models\WorkPermit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function index(): View
    {
        $today = now()->toDateString();
        $gateEvents = SecurityGateEvent::query()->with('user')->latest('occurred_at')->limit(30)->get();
        $loadingNumbers = LoadingPermit::query()->whereIn('id', $gateEvents->where('permit_type', 'loading')->pluck('permit_id'))->pluck('permit_number', 'id');
        $workNumbers = WorkPermit::query()->whereIn('id', $gateEvents->where('permit_type', 'work')->pluck('permit_id'))->pluck('permit_number', 'id');

        $gateEvents->each(function (SecurityGateEvent $event) use ($loadingNumbers, $workNumbers): void {
            $event->display_reference = $event->permit_type === 'loading'
                ? $loadingNumbers->get($event->permit_id)
                : $workNumbers->get($event->permit_id);
        });

        return view('security.index', [
            'loadingPermits' => LoadingPermit::query()->where('status', 'approved')->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->whereNull('revoked_at')->latest()->get(),
            'workPermits' => WorkPermit::query()->where('status', 'approved')->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->whereNull('revoked_at')->withCount('workers')->latest()->get(),
            'gateEvents' => $gateEvents,
            'checkedInCount' => SecurityGateEvent::query()->whereDate('occurred_at', $today)->where('event', 'check_in')->count(),
            'checkedOutCount' => SecurityGateEvent::query()->whereDate('occurred_at', $today)->where('event', 'check_out')->count(),
        ]);
    }

    public function readNotification(Request $request, PermitNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->markRead();

        return redirect()->route('security.index');
    }
}
