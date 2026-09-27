<?php

namespace App\Http\Controllers;

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
        $reference = $request->query('reference', '');
        return view('portal.track', compact('reference'));
    }
}
