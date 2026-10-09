<?php

namespace App\Services;

use App\Models\LoadingPermit;
use App\Models\WorkPermit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
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
        $pdf = Pdf::loadView('pdf.loading-permit', [
            'permit' => $permit,
            ...$this->assets(route('scanner.verify', ['token' => $permit->barcode_token])),
        ])
            ->setPaper('a4');
        $filename = $this->filename('loading-unloading', $permit->permit_number);
        $response = $inline ? $pdf->stream($filename) : $pdf->download($filename);

        return $this->secureDownload($response);
    }

    private function workPermitResponse(WorkPermit $permit, bool $inline): Response
    {
        $pdf = Pdf::loadView('pdf.work-permit', [
            'permit' => $permit,
            ...$this->assets(route('scanner.verify', ['token' => $permit->public_token])),
        ])
            ->setPaper('a4');
        $filename = $this->filename('izin-kerja', $permit->permit_number);
        $response = $inline ? $pdf->stream($filename) : $pdf->download($filename);

        return $this->secureDownload($response);
    }

    private function assets(string $verificationUrl): array
    {
        $logo = base64_encode((string) file_get_contents(public_path('logo.png')));
        $qrCode = (string) QrCode::format('svg')->size(116)->margin(0)->generate($verificationUrl);

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
