<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\ScannerSetting;
use App\Models\WorkPermit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class ScannerController extends Controller
{
    /**
     * Halaman scanner kamera (public, tanpa login).
     */
    public function index(): Response
    {
        return response()
            ->view('scanner.index', [
                'scannerSetting' => ScannerSetting::current(),
            ])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    /**
     * API endpoint verifikasi barcode token dari QR scan.
     * Mengembalikan JSON.
     */
    public function verify(Request $request): JsonResponse|RedirectResponse
    {
        $token = trim((string) $request->query('token', ''));

        if (! $request->expectsJson()) {
            return redirect()->route('scanner.index', array_filter(['token' => $token]));
        }

        $locationError = $this->locationError($request, ScannerSetting::current());
        if ($locationError) {
            return $locationError;
        }

        if (! preg_match('/\A[A-Za-z0-9]{64}\z/', $token)) {
            return $this->json([
                'valid' => false,
                'status' => 'invalid',
                'message' => 'Token tidak valid.',
            ], 400);
        }

        $permit = LoadingPermit::where('barcode_token', $token)->first();

        if ($permit) {
            return $this->loadingPermitResponse($permit);
        }

        $workPermit = WorkPermit::where('public_token', $token)->first();

        if (! $workPermit) {
            return $this->json([
                'valid' => false,
                'status' => 'not_found',
                'message' => 'Surat izin tidak ditemukan. Barcode tidak terdaftar dalam sistem.',
            ], 404);
        }

        if ($workPermit->status !== 'approved') {
            return $this->json([
                'valid' => false,
                'status' => 'not_approved',
                'message' => 'Surat izin ini belum atau tidak disetujui.',
                'permit_number' => $workPermit->permit_number,
            ], 422);
        }

        if (now()->isAfter($workPermit->end_date->copy()->endOfDay())) {
            return $this->json([
                'valid' => false,
                'status' => 'expired',
                'message' => 'Surat izin sudah melewati masa berlaku.',
                'permit_number' => $workPermit->permit_number,
                'expired_at' => $workPermit->end_date->format('d M Y').' 23:59 WITA',
            ], 422);
        }

        return $this->json([
            'valid' => true,
            'status' => 'active',
            'message' => 'Surat izin valid dan masih berlaku.',
            'permit_type' => 'work',
            'permit_number' => $workPermit->permit_number,
            'tenant_name' => $workPermit->contractor_name,
            'direction' => $workPermit->work_category_label,
            'start_date' => $workPermit->start_date->format('d M Y'),
            'end_date' => $workPermit->end_date->format('d M Y'),
            'movement_time' => $workPermit->work_schedule_label,
            'movement_label' => 'Waktu Kerja',
            'expires_at' => $workPermit->end_date->format('d M Y').' 23:59 WITA',
        ]);
    }

    private function locationError(Request $request, ScannerSetting $setting): ?JsonResponse
    {
        if (! $setting->requiresLocation()) {
            return null;
        }

        $validator = Validator::make($request->query(), [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:10000'],
        ]);

        if ($validator->fails()) {
            return $this->json([
                'valid' => false,
                'status' => 'location_required',
                'message' => 'Lokasi perangkat diperlukan untuk menggunakan scanner di area ini.',
            ], 428);
        }

        $coordinates = $validator->validated();
        $accuracy = (float) $coordinates['accuracy'];

        if ($accuracy > (int) $setting->radius_meters) {
            return $this->json([
                'valid' => false,
                'status' => 'location_inaccurate',
                'message' => 'Akurasi lokasi belum memadai. Aktifkan lokasi presisi lalu coba kembali di area scanner.',
            ], 422);
        }

        $distance = $setting->distanceFrom(
            (float) $coordinates['latitude'],
            (float) $coordinates['longitude'],
        );

        if ($distance > (int) $setting->radius_meters) {
            return $this->json([
                'valid' => false,
                'status' => 'location_denied',
                'message' => 'Scanner hanya dapat digunakan dari area yang telah ditentukan.',
            ], 403);
        }

        return null;
    }

    private function loadingPermitResponse(LoadingPermit $permit): JsonResponse
    {
        if ($permit->status !== 'approved') {
            return $this->json([
                'valid' => false,
                'status' => 'not_approved',
                'message' => 'Surat izin ini belum atau tidak disetujui.',
                'permit_number' => $permit->permit_number,
            ], 422);
        }

        if ($permit->isExpired()) {
            return $this->json([
                'valid' => false,
                'status' => 'expired',
                'message' => 'Surat izin sudah melewati masa berlaku.',
                'permit_number' => $permit->permit_number,
                'expired_at' => $permit->barcode_expires_at?->format('d M Y, H:i').' WITA',
            ], 422);
        }

        return $this->json([
            'valid' => true,
            'status' => 'active',
            'permit_type' => 'loading',
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

    private function json(array $data, int $status = 200): JsonResponse
    {
        return response()
            ->json($data, $status)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
