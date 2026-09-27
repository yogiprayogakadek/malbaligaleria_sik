<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    /**
     * Halaman Beranda Portal Perizinan Tenant.
     */
    public function index()
    {
        return view('portal.dashboard');
    }

    /**
     * Halaman Bantuan & Panduan Tenant.
     */
    public function help()
    {
        return view('portal.help');
    }

    /**
     * Halaman Pelacakan / Cek Status Izin.
     */
    public function track(Request $request)
    {
        $rawNumber = $request->input('permit_number')
            ?? $request->query('permit_number')
            ?? $request->query('reference')
            ?? '';

        $reference = trim((string) $rawNumber);
        $permit = null;
        $searched = false;

        if ($reference !== '') {
            $searched = true;

            // Pencarian exact case-insensitive & trimmed
            $permit = LoadingPermit::whereRaw('LOWER(TRIM(permit_number)) = ?', [strtolower($reference)])->first();

            // Fallback: pencarian parsial jika input format sedikit berbeda
            if (!$permit) {
                $permit = LoadingPermit::whereRaw('LOWER(permit_number) LIKE ?', ['%' . strtolower($reference) . '%'])->first();
            }
        }

        return view('portal.track', compact('reference', 'permit', 'searched'));
    }
}
