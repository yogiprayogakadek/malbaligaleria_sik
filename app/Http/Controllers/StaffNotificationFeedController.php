<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\WorkPermit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffNotificationFeedController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'category' => ['nullable', 'in:all,loading,work,finance'],
        ]);
        $afterId = (int) ($validated['after_id'] ?? 0);
        $user = $request->user();
        $readRoute = match (true) {
            $user->isAdmin() => 'admin.notifications.read',
            $user->isSecretary() => 'secretary.notifications.read',
            $user->division === 'MEP' => 'mep.notifications.read',
            $user->division === 'FIN' => 'finance.notifications.read',
            default => 'tr.notifications.read',
        };
        $notifications = PermitNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(20)
            ->get();

        return response()->json([
            'latest_id' => $notifications->max('id') ?? $afterId,
            'pending_count' => match (true) {
                $user->isSecretary() => PermitNotification::where('user_id', $user->id)->whereNull('read_at')->count(),
                $user->division === 'MEP' => WorkPermit::needsMepAction()->count(),
                $user->division === 'FIN' => WorkPermit::where('assigned_division', 'MEP')->where('status', 'payment_review')->count(),
                $user->division === 'TR' && ($validated['category'] ?? null) === 'work' => WorkPermit::needsTrAction()->count(),
                default => LoadingPermit::where('status', 'pending')->count(),
            },
            'notifications' => $notifications->map(fn (PermitNotification $notification): array => [
                'id' => $notification->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'read_url' => route($readRoute, $notification),
                'category' => $notification->work_permit_id ? 'work' : 'loading',
            ])->values(),
        ]);
    }
}
