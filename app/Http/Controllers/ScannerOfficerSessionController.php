<?php

namespace App\Http\Controllers;

use App\Models\SecurityOfficer;
use App\Services\ScannerOfficerSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ScannerOfficerSessionController extends Controller
{
    public function store(Request $request, ScannerOfficerSession $session): RedirectResponse
    {
        $data = $request->validate([
            'officer_id' => ['required', 'string', 'max:60'],
            'pin' => ['required', 'digits:6'],
        ]);

        $officer = SecurityOfficer::query()
            ->where('officer_id', mb_strtoupper(trim($data['officer_id'])))
            ->where('is_active', true)
            ->first();

        if (! $officer || ! Hash::check($data['pin'], $officer->pin_hash)) {
            return back()->withErrors(['scanner_login' => 'Nomor ID atau PIN tidak sesuai.'])->withInput($request->only('officer_id'));
        }

        $session->start($request, $officer);

        return redirect()->route('scanner.index')->with('scanner_success', 'Sesi shift aktif. Scanner siap digunakan.');
    }

    public function destroy(Request $request, ScannerOfficerSession $session): RedirectResponse
    {
        $session->end($request);

        return redirect()->route('scanner.index')->with('scanner_success', 'Sesi shift telah diakhiri.');
    }
}
