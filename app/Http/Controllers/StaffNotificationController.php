<?php

namespace App\Http\Controllers;

use App\Models\PermitNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StaffNotificationController extends Controller
{
    public function clear(Request $request): RedirectResponse
    {
        PermitNotification::where('user_id', $request->user()->id)->delete();

        return back()->with('success', 'Semua notifikasi telah dihapus.');
    }
}
