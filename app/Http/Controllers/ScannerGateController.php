<?php

namespace App\Http\Controllers;

use App\Contracts\MalwareScanner;
use App\Exceptions\OfficialDocumentIntegrityException;
use App\Models\LoadingPermit;
use App\Models\ScannerIpBlock;
use App\Models\ScannerSetting;
use App\Models\SecurityGateEvent;
use App\Services\PermitPdfService;
use App\Services\ScannerEvidenceService;
use App\Services\ScannerOfficerSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class ScannerGateController extends Controller
{
    public function store(
        Request $request,
        PermitPdfService $pdf,
        ScannerOfficerSession $officerSession,
        ScannerEvidenceService $evidenceService,
        MalwareScanner $malwareScanner,
    ): JsonResponse {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64', 'regex:/^[A-Za-z0-9]+$/'],
            'event' => ['required', Rule::in(['check_in', 'check_out'])],
            'challenge' => ['required', 'string', 'size:64'],
            'evidence_photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'between:0,10000'],
        ]);
        $this->authorizeSource($request);
        $this->authorizeLocation($data, ScannerSetting::current());

        $officer = $officerSession->current($request);
        abort_unless($officer, 401, 'Sesi petugas telah berakhir. Masukkan kembali nomor ID dan PIN.');
        abort_unless(
            $officerSession->consumeChallenge($request, $officer, $data['token'], $data['challenge']),
            419,
            'Verifikasi surat telah kedaluwarsa. Pindai kembali QR sebelum mengambil foto.',
        );

        $permit = LoadingPermit::query()->where('barcode_token', $data['token'])->first();
        abort_unless($permit, 404, 'Surat izin loading/unloading tidak ditemukan.');
        abort_if($permit->revoked_at || $permit->status !== 'approved', 409, 'Surat izin tidak aktif.');
        abort_if($permit->isExpired(), 409, 'Surat izin sudah kedaluwarsa.');
        try {
            $pdf->assertLoadingPermitIntegrity($permit);
        } catch (OfficialDocumentIntegrityException) {
            abort(503, 'Integritas dokumen tidak dapat diverifikasi. Hubungi pengelola sebelum memberikan akses.');
        }

        $malwareScanner->scan($request->file('evidence_photo'), 'evidence_photo');
        $evidence = $evidenceService->store(
            $request->file('evidence_photo'),
            $permit->permit_number,
            $officer->officer_id,
            $data['event'],
        );

        try {
            $event = DB::transaction(function () use ($request, $data, $permit, $officer, $evidence): SecurityGateEvent {
                LoadingPermit::query()->whereKey($permit->id)->lockForUpdate()->firstOrFail();
                $latest = SecurityGateEvent::query()
                    ->where('permit_type', 'loading')
                    ->where('permit_id', $permit->id)
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();
                $expected = $latest?->event === 'check_in' ? 'check_out' : 'check_in';
                abort_unless($data['event'] === $expected, 409, $expected === 'check_in' ? 'Surat ini belum dapat check-out.' : 'Surat ini sudah check-in dan belum check-out.');

                return SecurityGateEvent::query()->create([
                    'permit_type' => 'loading',
                    'permit_id' => $permit->id,
                    'event' => $data['event'],
                    'user_id' => $request->user()?->id,
                    'security_officer_id' => $officer->id,
                    'token_hash' => hash('sha256', $data['token']),
                    'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), (string) config('app.key')) : null,
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'accuracy' => $data['accuracy'] ?? null,
                    'evidence_path' => $evidence['path'],
                    'evidence_sha256' => $evidence['sha256'],
                    'occurred_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($evidence['path']);
            throw $exception;
        }

        return response()->json([
            'message' => $event->event === 'check_in' ? 'Check-in berhasil dicatat.' : 'Check-out berhasil dicatat.',
            'gate_status' => $event->event,
            'occurred_at' => $event->occurred_at->format('d M Y, H:i').' WITA',
            'officer_name' => $officer->name,
        ]);
    }

    private function authorizeLocation(array $data, ScannerSetting $setting): void
    {
        if (! $setting->requiresLocation()) {
            return;
        }

        abort_unless(isset($data['latitude'], $data['longitude'], $data['accuracy']), 428, 'Lokasi perangkat diperlukan.');
        abort_if((float) $data['accuracy'] > $setting->radius_meters, 422, 'Akurasi lokasi belum memadai.');
        abort_if($setting->distanceFrom((float) $data['latitude'], (float) $data['longitude']) > $setting->radius_meters, 403, 'Perangkat berada di luar area scanner.');
    }

    private function authorizeSource(Request $request): void
    {
        if (! $request->ip()) {
            return;
        }

        $ipHash = hash_hmac('sha256', $request->ip(), (string) config('app.key'));
        $isBlocked = ScannerIpBlock::query()
            ->where('ip_hash', $ipHash)
            ->whereNull('unblocked_at')
            ->where('blocked_until', '>', now())
            ->exists();

        abort_if($isBlocked, 429, 'Akses scanner dibatasi sementara.');
    }
}
