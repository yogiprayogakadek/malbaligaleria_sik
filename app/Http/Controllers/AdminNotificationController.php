<?php

namespace App\Http\Controllers;

use App\Models\PermitNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class AdminNotificationController extends Controller
{
    public function read(PermitNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === Auth::id(), 403);
        $notification->markRead();

        return $notification->permit
            ? redirect()->route('admin.loading.show', $notification->permit->permit_number)
            : redirect()->route('admin.dashboard');
    }
}
