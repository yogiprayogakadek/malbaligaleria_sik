<?php

namespace App\Http\Controllers;

use App\Contracts\MalwareScanner;
use App\Exceptions\OfficialDocumentIntegrityException;
use App\Models\LoadingPermit;
use App\Models\OfficialLetterCode;
use App\Models\PermitNotification;
use App\Models\ScannerIpBlock;
use App\Models\ScannerSetting;
use App\Models\SecurityGateEvent;
use App\Models\User;
use App\Services\PermitPdfService;
use App\Services\ScannerEvidenceService;
use App\Services\ScannerOfficerSession;
use App\Services\SecurityGateEvidenceResponse;
use App\Services\WhatsappNotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ScannerGateController extends Controller
{
    public function store(
        Request $request,
        PermitPdfService $pdf,
        ScannerOfficerSession $officerSession,
        ScannerEvidenceService $evidenceService,
        MalwareScanner $malwareScanner,
        WhatsappNotificationDispatcher $whatsapp,
    ): JsonResponse {
        if ($request->filled('letter_code')) {
            $request->merge([
                'letter_code' => strtoupper(str_replace('-', '', trim((string) $request->input('letter_code')))),
            ]);
        }

        $data = $request->validate([
            'token' => ['nullable', 'string', 'size:64', 'regex:/^[A-Za-z0-9]+$/'],
            'letter_code' => ['nullable', 'string', 'size:8', 'regex:/^[A-HJ-NP-Z2-9]+$/'],
            'event' => ['required', Rule::in(['check_in', 'check_out'])],
            'item_quantity' => ['nullable', 'integer', 'min:1'],
            'security_note' => ['nullable', 'string', 'max:1000'],
            'challenge' => ['required', 'string', 'size:64'],
            'evidence_photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'between:0,10000'],
        ]);
        if (isset($data['token']) === isset($data['letter_code'])) {
            throw ValidationException::withMessages([
                'token' => 'Gunakan token QR atau Kode Surat, tidak keduanya.',
            ]);
        }
        $this->authorizeSource($request);
        $this->authorizeLocation($data, ScannerSetting::current());

        $officer = $officerSession->current($request);
        abort_unless($officer, 401, 'Sesi petugas telah berakhir. Masukkan kembali nomor ID dan PIN.');

        $permit = isset($data['letter_code'])
            ? OfficialLetterCode::query()
                ->where('code', $data['letter_code'])
                ->where('permit_type', 'loading')
                ->where('is_active', true)
                ->first()?->resolvePermit()
            : LoadingPermit::query()->where('barcode_token', $data['token'])->first();
        abort_unless($permit, 404, 'Surat izin loading/unloading tidak ditemukan.');
        abort_if($permit->revoked_at || $permit->status !== 'approved', 409, 'Surat izin tidak aktif.');
        abort_if($permit->isExpired(), 409, 'Surat izin sudah kedaluwarsa.');
        $this->validateEventDirection($permit, $data['event']);
        abort_unless(
            $officerSession->consumeChallenge($request, $officer, $permit->barcode_token, $data['challenge']),
            419,
            'Verifikasi surat telah kedaluwarsa. Pindai kembali QR sebelum mengambil foto.',
        );

        $this->validateOutboundQuantity($permit, $data);
        $progress = $permit->outboundProgress();
        abort_if($progress['completed'] ?? false, 409, 'Seluruh jumlah barang keluar telah tercatat. Surat hanya dapat dilihat.');
        if ($data['event'] === 'check_out' && $permit->tracksOutboundQuantity() && $data['item_quantity'] > $progress['remaining']) {
            throw ValidationException::withMessages([
                'item_quantity' => 'Jumlah barang keluar melebihi sisa pada surat.',
            ]);
        }
        $reviewer = User::query()->validatorForDivision('TR')->find($permit->reviewed_by);
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
            $event = DB::transaction(function () use ($request, $data, $permit, $officer, $evidence, $reviewer): SecurityGateEvent {
                $lockedPermit = LoadingPermit::query()->whereKey($permit->id)->lockForUpdate()->firstOrFail();
                abort_if($lockedPermit->revoked_at || $lockedPermit->status !== 'approved', 409, 'Surat izin tidak aktif.');
                abort_if($lockedPermit->isExpired(), 409, 'Surat izin sudah kedaluwarsa.');
                $this->validateEventDirection($lockedPermit, $data['event']);
                $progress = $lockedPermit->outboundProgress();
                abort_if($progress['completed'] ?? false, 409, 'Seluruh jumlah barang keluar telah tercatat. Surat hanya dapat dilihat.');
                if ($data['event'] === 'check_out'
                    && $lockedPermit->tracksOutboundQuantity()
                    && $data['item_quantity'] > $progress['remaining']) {
                    throw ValidationException::withMessages([
                        'item_quantity' => 'Jumlah barang keluar melebihi sisa pada surat.',
                    ]);
                }
                $event = SecurityGateEvent::query()->create([
                    'permit_type' => 'loading',
                    'permit_id' => $permit->id,
                    'event' => $data['event'],
                    'item_quantity' => $data['event'] === 'check_out' && $lockedPermit->tracksOutboundQuantity()
                        ? $data['item_quantity']
                        : null,
                    'security_note' => filled($data['security_note'] ?? null) ? trim($data['security_note']) : null,
                    'user_id' => $request->user()?->id,
                    'security_officer_id' => $officer->id,
                    'token_hash' => hash('sha256', $lockedPermit->barcode_token),
                    'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), (string) config('app.key')) : null,
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'accuracy' => $data['accuracy'] ?? null,
                    'evidence_path' => $evidence['path'],
                    'evidence_sha256' => $evidence['sha256'],
                    'occurred_at' => now(),
                ]);

                if ($reviewer) {
                    $movement = $event->event === 'check_in' ? 'barang masuk' : 'barang keluar';
                    $quantity = $event->item_quantity ? " Jumlah: {$event->item_quantity} {$lockedPermit->item_unit}." : '';
                    $note = $event->security_note ? ' Catatan: '.mb_substr($event->security_note, 0, 180) : '';
                    PermitNotification::query()->create([
                        'user_id' => $reviewer->id,
                        'permit_id' => $permit->id,
                        'type' => 'gate_evidence_uploaded',
                        'title' => $event->event === 'check_in' ? 'Foto Bukti Barang Masuk' : 'Foto Bukti Barang Keluar',
                        'body' => "{$officer->name} mengunggah foto bukti {$movement} untuk #{$lockedPermit->permit_number}.{$quantity}{$note}",
                    ]);
                }

                return $event;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($evidence['path']);
            throw $exception;
        }

        try {
            $whatsapp->gateEvidence($permit, $event, $officer, $reviewer);
        } catch (Throwable $exception) {
            Log::warning('Notifikasi WhatsApp bukti gerbang gagal dimasukkan ke antrean.', [
                'security_gate_event_id' => $event->id,
                'permit_id' => $permit->id,
                'reviewer_id' => $reviewer?->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        $permit->refresh();
        $progress = $permit->outboundProgress();

        return response()->json([
            'message' => $event->event === 'check_in' ? 'Check-in berhasil dicatat.' : 'Check-out berhasil dicatat.',
            'gate_status' => $event->event,
            'occurred_at' => $event->occurred_at->format('d M Y, H:i').' WITA',
            'officer_name' => $officer->name,
            'outbound_progress' => $progress,
            'gate_event' => [
                'id' => $event->id,
                'event' => $event->event,
                'event_label' => $event->event === 'check_in' ? 'Barang masuk' : 'Barang keluar',
                'item_quantity' => $event->item_quantity,
                'item_unit' => $event->item_quantity ? $permit->item_unit : null,
                'security_note' => $event->security_note,
                'officer_name' => $officer->name,
                'occurred_at' => $event->occurred_at->format('d M Y, H:i').' WITA',
                'evidence_url' => URL::temporarySignedRoute(
                    'scanner.gate-events.evidence',
                    now()->addMinutes(5),
                    ['securityGateEvent' => $event->id, 'permit' => $permit->id],
                ),
            ],
        ]);
    }

    public function evidence(
        Request $request,
        SecurityGateEvent $securityGateEvent,
        ScannerOfficerSession $officerSession,
        SecurityGateEvidenceResponse $evidence,
    ): BinaryFileResponse {
        abort_unless($officerSession->current($request), 401, 'Sesi petugas telah berakhir.');
        abort_unless(
            $securityGateEvent->permit_type === 'loading'
                && $securityGateEvent->permit_id === (int) $request->query('permit'),
            404,
        );

        return $evidence->make($securityGateEvent);
    }

    private function validateOutboundQuantity(LoadingPermit $permit, array $data): void
    {
        $requiresQuantity = $data['event'] === 'check_out' && $permit->tracksOutboundQuantity();
        if ($requiresQuantity && ! isset($data['item_quantity'])) {
            throw ValidationException::withMessages([
                'item_quantity' => 'Jumlah barang keluar wajib diisi.',
            ]);
        }
        if (! $requiresQuantity && isset($data['item_quantity'])) {
            throw ValidationException::withMessages([
                'item_quantity' => 'Jumlah barang hanya diisi saat mencatat barang keluar.',
            ]);
        }
    }

    private function validateEventDirection(LoadingPermit $permit, string $event): void
    {
        $allowed = match ($permit->direction) {
            'in' => ['check_in'],
            'out' => ['check_out'],
            'both' => ['check_in', 'check_out'],
            default => [],
        };

        abort_unless(in_array($event, $allowed, true), 409, 'Jenis bukti tidak sesuai dengan arah pada surat izin.');
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
