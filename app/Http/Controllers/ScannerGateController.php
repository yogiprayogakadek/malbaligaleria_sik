<?php

namespace App\Http\Controllers;

use App\Exceptions\OfficialDocumentIntegrityException;
use App\Models\LoadingPermit;
use App\Models\ScannerIpBlock;
use App\Models\ScannerSetting;
use App\Models\SecurityGateEvent;
use App\Models\WorkPermit;
use App\Services\PermitPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ScannerGateController extends Controller
{
    public function store(Request $request, PermitPdfService $pdf): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64', 'regex:/^[A-Za-z0-9]+$/'],
            'event' => ['required', Rule::in(['check_in', 'check_out'])],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'between:0,10000'],
        ]);
        $this->authorizeSource($request);
        $this->authorizeLocation($data, ScannerSetting::current());

        [$type, $permit] = $this->permit($data['token']);
        abort_unless($permit, 404, 'Surat izin tidak ditemukan.');
        abort_if($permit->revoked_at || $permit->status !== 'approved', 409, 'Surat izin tidak aktif.');
        abort_if($type === 'loading' ? $permit->isExpired() : now()->isAfter($permit->end_date->copy()->endOfDay()), 409, 'Surat izin sudah kedaluwarsa.');
        try {
            $type === 'loading'
                ? $pdf->assertLoadingPermitIntegrity($permit)
                : $pdf->assertWorkPermitIntegrity($permit);
        } catch (OfficialDocumentIntegrityException) {
            abort(503, 'Integritas dokumen tidak dapat diverifikasi. Hubungi pengelola sebelum memberikan akses.');
        }

        $event = DB::transaction(function () use ($request, $data, $type, $permit): SecurityGateEvent {
            ($type === 'loading' ? LoadingPermit::query() : WorkPermit::query())
                ->whereKey($permit->id)
                ->lockForUpdate()
                ->firstOrFail();
            $latest = SecurityGateEvent::query()->where('permit_type', $type)->where('permit_id', $permit->id)->lockForUpdate()->latest('id')->first();
            $expected = $latest?->event === 'check_in' ? 'check_out' : 'check_in';
            abort_unless($data['event'] === $expected, 409, $expected === 'check_in' ? 'Surat ini belum dapat check-out.' : 'Surat ini sudah check-in dan belum check-out.');

            return SecurityGateEvent::query()->create([
                'permit_type' => $type,
                'permit_id' => $permit->id,
                'event' => $data['event'],
                'user_id' => $request->user()?->id,
                'token_hash' => hash('sha256', $data['token']),
                'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), (string) config('app.key')) : null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'accuracy' => $data['accuracy'] ?? null,
                'occurred_at' => now(),
            ]);
        });

        return response()->json([
            'message' => $event->event === 'check_in' ? 'Check-in berhasil dicatat.' : 'Check-out berhasil dicatat.',
            'gate_status' => $event->event,
            'occurred_at' => $event->occurred_at->format('d M Y, H:i').' WITA',
        ]);
    }

    private function permit(string $token): array
    {
        if ($permit = LoadingPermit::query()->where('barcode_token', $token)->first()) {
            return ['loading', $permit];
        }

        return ['work', WorkPermit::query()->where('public_token', $token)->first()];
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
