<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $rawPhone = $request->input('phone')
            ?? $request->query('phone')
            ?? '';

        $reference = trim((string) $rawNumber);
        $phoneInput = trim((string) $rawPhone);
        $permit = null;
        $searched = false;
        $phoneMismatch = false;

        if ($reference !== '') {
            $searched = true;

            // Cari permohonan berdasarkan nomor surat (case-insensitive & trim)
            $candidate = LoadingPermit::whereRaw('LOWER(TRIM(permit_number)) = ?', [strtolower($reference)])->first();

            if (! $candidate) {
                $candidate = LoadingPermit::whereRaw('LOWER(permit_number) LIKE ?', ['%' . strtolower($reference) . '%'])->first();
            }

            if ($candidate) {
                // Jika sedang login sebagai pemilik surat atau validator/admin, bypass verifikasi nomor HP
                $isAuthorized = Auth::check() && (
                    $candidate->user_id === Auth::id() ||
                    Auth::user()->isValidator() ||
                    Auth::user()->isAdmin()
                );

                if ($isAuthorized) {
                    $permit = $candidate;
                } else {
                    // Verifikasi nomor HP/WhatsApp PIC (bisa nomor lengkap atau 4+ digit terakhir)
                    $cleanInput = preg_replace('/\D+/', '', $phoneInput);
                    $cleanStored = preg_replace('/\D+/', '', (string) $candidate->applicant_phone);

                    // Normalisasi awalan 62 -> 0
                    $normInput = str_starts_with($cleanInput, '62') ? '0' . substr($cleanInput, 2) : $cleanInput;
                    $normStored = str_starts_with($cleanStored, '62') ? '0' . substr($cleanStored, 2) : $cleanStored;

                    $matches = false;
                    if ($cleanInput !== '' && $cleanStored !== '') {
                        if ($cleanInput === $cleanStored || $normInput === $normStored) {
                            $matches = true;
                        } elseif (ltrim($cleanInput, '0') === ltrim($cleanStored, '0')) {
                            $matches = true;
                        } elseif (strlen($cleanInput) >= 4 && str_ends_with($cleanStored, $cleanInput)) {
                            $matches = true;
                        }
                    }

                    if ($matches) {
                        $permit = $candidate;
                    } else {
                        $phoneMismatch = true;
                    }
                }
            }
        }

        return view('portal.track', compact('reference', 'phoneInput', 'permit', 'searched', 'phoneMismatch'));
    }
}
