<?php

namespace App\Services;

use App\Exceptions\OfficialDocumentIntegrityException;
use App\Models\LoadingPermit;
use App\Models\WorkPermit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PermitPdfService
{
    public function loadingPermit(LoadingPermit $permit): Response
    {
        return $this->loadingPermitResponse($permit, false);
    }

    public function loadingPermitInline(LoadingPermit $permit): Response
    {
        return $this->loadingPermitResponse($permit, true);
    }

    public function workPermit(WorkPermit $permit): Response
    {
        return $this->workPermitResponse($permit, false);
    }

    public function workPermitInline(WorkPermit $permit): Response
    {
        return $this->workPermitResponse($permit, true);
    }

    private function loadingPermitResponse(LoadingPermit $permit, bool $inline): Response
    {
        $document = $this->ensureLoadingPermit($permit);
        $filename = $this->filename('loading-unloading', $permit->permit_number);

        return $this->documentResponse($document, $filename, $inline);
    }

    private function workPermitResponse(WorkPermit $permit, bool $inline): Response
    {
        $document = $this->ensureWorkPermit($permit);
        $filename = $this->filename($permit->isEventPermit() ? 'izin-event' : 'izin-kerja', $permit->permit_number);

        return $this->documentResponse($document, $filename, $inline);
    }

    public function sealLoadingPermit(LoadingPermit $permit): LoadingPermit
    {
        abort_unless($permit->status === 'approved' && ! $permit->revoked_at, 409, 'Surat loading belum dapat diterbitkan.');

        return $this->seal(
            $permit->loadMissing('reviewer'),
            'loading',
            fn (LoadingPermit $sealedPermit): string => Pdf::loadView('pdf.loading-permit', [
                'permit' => $sealedPermit,
                ...$this->assets(route('scanner.verify', ['token' => $sealedPermit->barcode_token])),
            ])->setPaper('a4')->output(),
        );
    }

    public function sealWorkPermit(WorkPermit $permit): WorkPermit
    {
        abort_unless(in_array($permit->status, ['approved', 'completed', 'refund_processing', 'refunded'], true) && ! $permit->revoked_at, 409, 'Surat izin kerja belum dapat diterbitkan.');

        $type = $permit->isEventPermit() ? 'event' : 'work';
        $view = $permit->isEventPermit() ? 'pdf.event-permit' : 'pdf.work-permit';

        return $this->seal(
            $permit->loadMissing(['workers', 'reviewer']),
            $type,
            fn (WorkPermit $sealedPermit): string => Pdf::loadView($view, [
                'permit' => $sealedPermit,
                ...$this->assets(route('scanner.verify', ['token' => $sealedPermit->public_token])),
            ])->setPaper('a4')->output(),
        );
    }

    public function assertLoadingPermitIntegrity(LoadingPermit $permit): LoadingPermit
    {
        return $this->ensureLoadingPermit($permit);
    }

    public function assertWorkPermitIntegrity(WorkPermit $permit): WorkPermit
    {
        return $this->ensureWorkPermit($permit);
    }

    private function ensureLoadingPermit(LoadingPermit $permit): LoadingPermit
    {
        return DB::transaction(function () use ($permit): LoadingPermit {
            $locked = LoadingPermit::query()->lockForUpdate()->findOrFail($permit->id);

            return $this->hasDocumentMetadata($locked)
                ? $this->verify($locked)
                : $this->sealLoadingPermit($locked);
        });
    }

    private function ensureWorkPermit(WorkPermit $permit): WorkPermit
    {
        return DB::transaction(function () use ($permit): WorkPermit {
            $locked = WorkPermit::query()->lockForUpdate()->findOrFail($permit->id);

            return $this->hasDocumentMetadata($locked)
                ? $this->verify($locked)
                : $this->sealWorkPermit($locked);
        });
    }

    private function seal(LoadingPermit|WorkPermit $permit, string $type, callable $renderer): LoadingPermit|WorkPermit
    {
        if ($this->hasAnyDocumentMetadata($permit)) {
            return $this->verify($permit);
        }

        $permit->official_document_id = (string) Str::uuid();
        $content = $renderer($permit);
        $hash = hash('sha256', $content);
        $path = "permits/official/{$type}/{$permit->id}-{$hash}.pdf";

        if (! Storage::disk('local')->put($path, $content)) {
            throw new RuntimeException('Dokumen resmi gagal disimpan.');
        }

        $permit->official_document_path = $path;
        $permit->official_document_sha256 = $hash;
        $permit->official_document_issued_at = now();
        $permit->save();

        return $permit;
    }

    private function verify(LoadingPermit|WorkPermit $permit): LoadingPermit|WorkPermit
    {
        if (! $this->hasDocumentMetadata($permit)) {
            $this->integrityFailure($permit, 'metadata_incomplete');
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($permit->official_document_path)) {
            $this->integrityFailure($permit, 'file_missing');
        }

        $actualHash = hash('sha256', $disk->get($permit->official_document_path));
        if (! hash_equals($permit->official_document_sha256, $actualHash)) {
            $this->integrityFailure($permit, 'hash_mismatch');
        }

        return $permit;
    }

    private function hasDocumentMetadata(LoadingPermit|WorkPermit $permit): bool
    {
        return filled($permit->official_document_id)
            && filled($permit->official_document_path)
            && filled($permit->official_document_sha256)
            && $permit->official_document_issued_at !== null;
    }

    private function hasAnyDocumentMetadata(LoadingPermit|WorkPermit $permit): bool
    {
        return filled($permit->official_document_id)
            || filled($permit->official_document_path)
            || filled($permit->official_document_sha256)
            || $permit->official_document_issued_at !== null;
    }

    private function integrityFailure(LoadingPermit|WorkPermit $permit, string $reason): never
    {
        Log::critical('Integritas dokumen resmi gagal diverifikasi.', [
            'permit_type' => $permit instanceof LoadingPermit ? 'loading' : ($permit->isEventPermit() ? 'event' : 'work'),
            'permit_id' => $permit->id,
            'document_id' => $permit->official_document_id,
            'reason' => $reason,
        ]);

        throw new OfficialDocumentIntegrityException;
    }

    private function documentResponse(LoadingPermit|WorkPermit $permit, string $filename, bool $inline): Response
    {
        $content = Storage::disk('local')->get($permit->official_document_path);
        $disposition = $inline ? 'inline' : 'attachment';

        return $this->secureDownload(response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'X-Document-Id' => $permit->official_document_id,
            'X-Document-SHA256' => $permit->official_document_sha256,
        ]));
    }

    private function assets(string $verificationUrl): array
    {
        $logo = base64_encode((string) file_get_contents(public_path('logo.png')));
        $qrCode = (string) QrCode::format('svg')
            ->errorCorrection('H')
            ->size(116)
            ->margin(0)
            ->generate($verificationUrl);

        return [
            'logoDataUri' => "data:image/png;base64,{$logo}",
            'qrDataUri' => 'data:image/svg+xml;base64,'.base64_encode($qrCode),
        ];
    }

    private function filename(string $type, string $permitNumber): string
    {
        return 'surat-'.$type.'-'.Str::slug(str_replace('/', '-', $permitNumber)).'.pdf';
    }

    private function secureDownload(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
