<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffNotificationFeedController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);
        $afterId = (int) ($validated['after_id'] ?? 0);
        $user = $request->user();
        $readRoute = $user->isAdmin() ? 'admin.notifications.read' : 'tr.notifications.read';
        $notifications = PermitNotification::query()
            ->where('user_id', $user->id)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(20)
            ->get();

        return response()->json([
            'latest_id' => $notifications->max('id') ?? $afterId,
            'pending_count' => LoadingPermit::where('status', 'pending')->count(),
            'notifications' => $notifications->map(fn (PermitNotification $notification): array => [
                'id' => $notification->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'read_url' => route($readRoute, $notification),
            ])->values(),
        ]);
    }
}
