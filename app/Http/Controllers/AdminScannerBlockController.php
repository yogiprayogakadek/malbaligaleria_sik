<?php

namespace App\Http\Controllers;

use App\Models\ScannerIpBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminScannerBlockController extends Controller
{
    public function destroy(Request $request, ScannerIpBlock $scannerIpBlock): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);

        $scannerIpBlock->update([
            'blocked_until' => now(),
            'unblocked_at' => now(),
            'unblocked_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Blokir scanner berhasil dibuka. Aktivitas berikutnya tetap dicatat.');
    }
}
