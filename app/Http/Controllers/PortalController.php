<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
     * Halaman Pelacakan / Cek Status Izin dengan Standar Keamanan Tinggi.
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
        $securityError = null;

        // Jika ada pencarian
        if ($reference !== '' || $phoneInput !== '') {
            $searched = true;

            // Validasi Input Sisi Server yang Ketat (Strict Security Validation)
            $validator = Validator::make([
                'permit_number' => $reference,
                'phone'         => $phoneInput,
            ], [
                'permit_number' => ['required', 'string', 'min:5', 'max:50', 'regex:/^[A-Za-z0-9\/_\-]+$/'],
                'phone'         => ['required', 'string', 'min:9', 'max:25', 'regex:/^[0-9+\s\-()]+$/'],
            ], [
                'permit_number.required' => 'Nomor surat izin wajib diisi.',
                'permit_number.regex'    => 'Format nomor surat mengandung karakter yang tidak valid.',
                'phone.required'         => 'Nomor WhatsApp / HP PIC wajib diisi.',
                'phone.min'              => 'Nomor telepon minimal harus terdiri dari 9 digit angka.',
                'phone.regex'            => 'Nomor telepon hanya boleh memuat angka, tanda +, tanda -, spasi, atau kurung.',
            ]);

            if ($validator->fails()) {
                $securityError = $validator->errors()->first();
                return view('portal.track', compact('reference', 'phoneInput', 'permit', 'searched', 'securityError'));
            }

            // Normalisasi & hitung digit angka bersih
            $cleanInputPhone = preg_replace('/\D+/', '', $phoneInput);
            if (strlen($cleanInputPhone) < 9) {
                $securityError = 'Nomor WhatsApp / HP penanggung jawab harus memiliki minimal 9 digit angka.';
                return view('portal.track', compact('reference', 'phoneInput', 'permit', 'searched', 'securityError'));
            }

            // Cari permohonan berdasarkan nomor surat (case-insensitive & trim)
            $candidate = LoadingPermit::whereRaw('LOWER(TRIM(permit_number)) = ?', [strtolower($reference)])->first();

            // Verifikasi Ketat: Tanpa bypass login, nomor HP wajib terverifikasi penuh
            $isVerified = false;

            if ($candidate && !empty($candidate->applicant_phone)) {
                $cleanStoredPhone = preg_replace('/\D+/', '', (string) $candidate->applicant_phone);

                $normInput  = str_starts_with($cleanInputPhone, '62') ? '0' . substr($cleanInputPhone, 2) : $cleanInputPhone;
                $normStored = str_starts_with($cleanStoredPhone, '62') ? '0' . substr($cleanStoredPhone, 2) : $cleanStoredPhone;

                // Verifikasi exact match atau normalized match (08xx vs 628xx)
                if ($cleanInputPhone === $cleanStoredPhone || $normInput === $normStored) {
                    $isVerified = true;
                } elseif (ltrim($cleanInputPhone, '0') === ltrim($cleanStoredPhone, '0')) {
                    $isVerified = true;
                }
            }

            if ($isVerified && $candidate) {
                $permit = $candidate;
            } else {
                // Pesan error seragam untuk mencegah Resource Enumeration / IDOR
                $securityError = 'Data permohonan tidak ditemukan atau nomor kontak PIC tidak sesuai. Pastikan kombinasi nomor surat dan nomor WhatsApp penanggung jawab benar.';
            }
        }

        return view('portal.track', compact('reference', 'phoneInput', 'permit', 'searched', 'securityError'));
    }
}
