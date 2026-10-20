<?php

namespace App\Http\Controllers;

use App\Exceptions\OfficialDocumentIntegrityException;
use App\Models\LoadingPermit;
use App\Models\ScannerIpBlock;
use App\Models\ScannerSetting;
use App\Models\ScannerVerificationLog;
use App\Models\SecurityGateEvent;
use App\Models\WorkPermit;
use App\Services\PermitPdfService;
use App\Services\ScannerAbuseMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

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
    public function verify(Request $request, PermitPdfService $pdf): JsonResponse|RedirectResponse
    {
        $token = trim((string) $request->query('token', ''));

        if (! $request->expectsJson()) {
            return redirect()->route('scanner.index', array_filter(['token' => $token]));
        }

        $ipHash = $request->ip()
            ? hash_hmac('sha256', $request->ip(), (string) config('app.key'))
            : null;
        $activeBlock = $ipHash ? ScannerIpBlock::query()
            ->where('ip_hash', $ipHash)
            ->whereNull('unblocked_at')
            ->where('blocked_until', '>', now())
            ->first() : null;
        if ($activeBlock) {
            return $this->json($request, [
                'valid' => false,
                'status' => 'blocked',
                'message' => 'Akses scanner dibatasi sementara karena aktivitas berulang. Hubungi administrator bila diperlukan.',
                'retry_after' => $activeBlock->blocked_until->diffInSeconds(now()),
            ], 429);
        }

        $locationError = $this->locationError($request, ScannerSetting::current());
        if ($locationError) {
            return $locationError;
        }

        if (! preg_match('/\A[A-Za-z0-9]{64}\z/', $token)) {
            return $this->json($request, [
                'valid' => false,
                'status' => 'invalid',
                'message' => 'Token tidak valid.',
            ], 400);
        }

        $permit = LoadingPermit::where('barcode_token', $token)->first();

        if ($permit) {
            return $this->loadingPermitResponse($request, $permit, $pdf);
        }

        $workPermit = WorkPermit::where('public_token', $token)->first();

        if (! $workPermit) {
            return $this->json($request, [
                'valid' => false,
                'status' => 'not_found',
                'message' => 'Surat izin tidak ditemukan. Barcode tidak terdaftar dalam sistem.',
            ], 404);
        }

        if ($workPermit->revoked_at) {
            return $this->json($request, [
                'valid' => false,
                'status' => 'revoked',
                'message' => 'Surat izin ini telah dicabut dan tidak lagi berlaku.',
                'permit_number' => $workPermit->permit_number,
            ], 410, $workPermit);
        }

        if ($workPermit->status !== 'approved') {
            return $this->json($request, [
                'valid' => false,
                'status' => 'not_approved',
                'message' => 'Surat izin ini belum atau tidak disetujui.',
                'permit_number' => $workPermit->permit_number,
            ], 422, $workPermit);
        }

        if (now()->isAfter($workPermit->end_date->copy()->endOfDay())) {
            return $this->json($request, [
                'valid' => false,
                'status' => 'expired',
                'message' => 'Surat izin sudah melewati masa berlaku.',
                'permit_number' => $workPermit->permit_number,
                'expired_at' => $workPermit->end_date->format('d M Y').' 23:59 WITA',
            ], 422, $workPermit);
        }

        try {
            $workPermit = $pdf->assertWorkPermitIntegrity($workPermit);
        } catch (OfficialDocumentIntegrityException) {
            return $this->json($request, [
                'valid' => false,
                'status' => 'integrity_error',
                'message' => 'Integritas dokumen tidak dapat diverifikasi. Hubungi pengelola sebelum memberikan akses.',
                'permit_number' => $workPermit->permit_number,
            ], 503, $workPermit);
        }

        return $this->json($request, [
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
            'document_id' => $workPermit->official_document_id,
            'document_fingerprint' => substr($workPermit->official_document_sha256, 0, 12),
            'gate_status' => $this->gateStatus('work', $workPermit->id),
        ], 200, $workPermit);
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
            return $this->json($request, [
                'valid' => false,
                'status' => 'location_required',
                'message' => 'Lokasi perangkat diperlukan untuk menggunakan scanner di area ini.',
            ], 428);
        }

        $coordinates = $validator->validated();
        $accuracy = (float) $coordinates['accuracy'];

        if ($accuracy > (int) $setting->radius_meters) {
            return $this->json($request, [
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
            return $this->json($request, [
                'valid' => false,
                'status' => 'location_denied',
                'message' => 'Scanner hanya dapat digunakan dari area yang telah ditentukan.',
            ], 403);
        }

        return null;
    }

    private function loadingPermitResponse(Request $request, LoadingPermit $permit, PermitPdfService $pdf): JsonResponse
    {
        $validUntil = $permit->barcodeValidUntil();

        if ($permit->revoked_at) {
            return $this->json($request, [
                'valid' => false,
                'status' => 'revoked',
                'message' => 'Surat izin ini telah dicabut dan tidak lagi berlaku.',
                'permit_number' => $permit->permit_number,
            ], 410, $permit);
        }

        if ($permit->status !== 'approved') {
            return $this->json($request, [
                'valid' => false,
                'status' => 'not_approved',
                'message' => 'Surat izin ini belum atau tidak disetujui.',
                'permit_number' => $permit->permit_number,
            ], 422, $permit);
        }

        if ($permit->isExpired()) {
            return $this->json($request, [
                'valid' => false,
                'status' => 'expired',
                'message' => 'Surat izin sudah melewati masa berlaku.',
                'permit_number' => $permit->permit_number,
                'expired_at' => $validUntil?->format('d M Y, H:i').' WITA',
            ], 422, $permit);
        }

        try {
            $permit = $pdf->assertLoadingPermitIntegrity($permit);
        } catch (OfficialDocumentIntegrityException) {
            return $this->json($request, [
                'valid' => false,
                'status' => 'integrity_error',
                'message' => 'Integritas dokumen tidak dapat diverifikasi. Hubungi pengelola sebelum memberikan akses.',
                'permit_number' => $permit->permit_number,
            ], 503, $permit);
        }

        return $this->json($request, [
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
            'expires_at' => $validUntil?->format('d M Y, H:i').' WITA',
            'document_id' => $permit->official_document_id,
            'document_fingerprint' => substr($permit->official_document_sha256, 0, 12),
            'gate_status' => $this->gateStatus('loading', $permit->id),
        ], 200, $permit);
    }

    private function gateStatus(string $type, int $permitId): ?string
    {
        return SecurityGateEvent::query()
            ->where('permit_type', $type)
            ->where('permit_id', $permitId)
            ->latest('id')
            ->value('event');
    }

    private function json(
        Request $request,
        array $data,
        int $status = 200,
        LoadingPermit|WorkPermit|null $permit = null,
    ): JsonResponse {
        $this->recordVerification($request, $data, $status, $permit);

        return response()
            ->json($data, $status)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    private function recordVerification(
        Request $request,
        array $data,
        int $status,
        LoadingPermit|WorkPermit|null $permit,
    ): void {
        $coordinate = static function (mixed $value, float $minimum, float $maximum): ?string {
            if (! is_numeric($value) || (float) $value < $minimum || (float) $value > $maximum) {
                return null;
            }

            return number_format((float) $value, 7, '.', '');
        };

        try {
            $log = ScannerVerificationLog::create([
                'user_id' => $request->user()?->id,
                'token_hash' => hash('sha256', trim((string) $request->query('token', ''))),
                'permit_type' => $permit instanceof LoadingPermit ? 'loading' : ($permit instanceof WorkPermit ? 'work' : null),
                'permit_id' => $permit?->id,
                'result' => (string) ($data['status'] ?? 'unknown'),
                'status_code' => $status,
                'ip_address' => $request->ip(),
                'ip_hash' => $request->ip()
                    ? hash_hmac('sha256', $request->ip(), (string) config('app.key'))
                    : null,
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'latitude' => $coordinate($request->query('latitude'), -90, 90),
                'longitude' => $coordinate($request->query('longitude'), -180, 180),
                'accuracy' => is_numeric($request->query('accuracy'))
                    ? min(10000, max(0, (float) $request->query('accuracy')))
                    : null,
            ]);
            app(ScannerAbuseMonitor::class)->inspect($log);
        } catch (Throwable $exception) {
            Log::warning('Log verifikasi scanner gagal disimpan.', [
                'result' => $data['status'] ?? 'unknown',
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
