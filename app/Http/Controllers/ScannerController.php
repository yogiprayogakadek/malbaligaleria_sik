<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use Illuminate\Http\Request;

class ScannerController extends Controller
{
    /**
     * Halaman scanner kamera (public, tanpa login).
     */
    public function index()
    {
        return view('scanner.index');
    }

    /**
     * API endpoint verifikasi barcode token dari QR scan.
     * Mengembalikan JSON.
     */
    public function verify(Request $request)
    {
        $token = trim($request->query('token', ''));

        if (! $token || strlen($token) < 16) {
            return response()->json([
                'valid' => false,
                'status' => 'invalid',
                'message' => 'Token tidak valid.',
            ], 400);
        }

        $permit = LoadingPermit::where('barcode_token', $token)->first();

        if (! $permit) {
            return response()->json([
                'valid' => false,
                'status' => 'not_found',
                'message' => 'Surat izin tidak ditemukan. Barcode tidak terdaftar dalam sistem.',
            ], 404);
        }

        if ($permit->status !== 'approved') {
            return response()->json([
                'valid' => false,
                'status' => 'not_approved',
                'message' => 'Surat izin ini belum atau tidak disetujui.',
                'permit_number' => $permit->permit_number,
            ], 422);
        }

        if ($permit->isExpired()) {
            return response()->json([
                'valid' => false,
                'status' => 'expired',
                'message' => 'Surat izin sudah melewati masa berlaku.',
                'permit_number' => $permit->permit_number,
                'expired_at' => $permit->barcode_expires_at?->format('d M Y, H:i').' WITA',
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'status' => 'active',
            'message' => 'Surat izin valid dan masih berlaku.',
            'permit_number' => $permit->permit_number,
            'tenant_name' => $permit->tenant_name,
            'direction' => $permit->direction_label,
            'start_date' => $permit->start_date->format('d M Y'),
            'end_date' => $permit->end_date->format('d M Y'),
            'movement_time' => $permit->movement_time_label,
            'movement_label' => $permit->movement_time_field_label,
            'item_count' => $permit->item_count,
            'expires_at' => $permit->barcode_expires_at?->format('d M Y, H:i').' WITA',
        ]);
    }
}
