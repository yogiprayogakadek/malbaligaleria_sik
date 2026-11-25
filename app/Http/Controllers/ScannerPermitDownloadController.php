<?php

namespace App\Http\Controllers;

use App\Exceptions\OfficialDocumentIntegrityException;
use App\Models\AuditLog;
use App\Models\LoadingPermit;
use App\Models\SecurityOfficer;
use App\Models\WorkPermit;
use App\Services\PermitPdfService;
use App\Services\ScannerOfficerSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ScannerPermitDownloadController extends Controller
{
    public function loading(
        Request $request,
        LoadingPermit $permit,
        ScannerOfficerSession $officerSession,
        PermitPdfService $pdf,
    ): Response {
        $officer = $this->officer($request, $officerSession);
        abort_unless(
            $permit->status === 'approved' && ! $permit->revoked_at && ! $permit->isExpired(),
            410,
            'Tautan unduhan sudah tidak berlaku. Pindai kembali surat untuk mengunduh.',
        );

        try {
            $response = $pdf->loadingPermit($permit);
        } catch (OfficialDocumentIntegrityException) {
            abort(409, 'Integritas dokumen tidak dapat diverifikasi.');
        }

        $this->recordDownload($request, $permit, $officer, 'loading');

        return $response;
    }

    public function work(
        Request $request,
        WorkPermit $permit,
        ScannerOfficerSession $officerSession,
        PermitPdfService $pdf,
    ): Response {
        $officer = $this->officer($request, $officerSession);
        abort_unless(
            $permit->status === 'approved'
                && ! $permit->revoked_at
                && ! now()->isAfter($permit->end_date->copy()->endOfDay()),
            410,
            'Tautan unduhan sudah tidak berlaku. Pindai kembali surat untuk mengunduh.',
        );

        try {
            $response = $pdf->workPermit($permit);
        } catch (OfficialDocumentIntegrityException) {
            abort(409, 'Integritas dokumen tidak dapat diverifikasi.');
        }

        $this->recordDownload($request, $permit, $officer, $permit->isEventPermit() ? 'event' : 'work');

        return $response;
    }

    private function officer(Request $request, ScannerOfficerSession $officerSession): SecurityOfficer
    {
        $officer = $officerSession->current($request);
        abort_unless($officer, 401, 'Sesi petugas security sudah berakhir.');

        return $officer;
    }

    private function recordDownload(
        Request $request,
        Model $permit,
        SecurityOfficer $officer,
        string $permitType,
    ): void {
        AuditLog::query()->create([
            'actor_id' => null,
            'event' => 'scanner_letter_downloaded',
            'auditable_type' => $permit::class,
            'auditable_id' => $permit->getKey(),
            'route_name' => $request->route()?->getName(),
            'request_method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => [],
            'new_values' => [
                'permit_type' => $permitType,
                'security_officer_id' => $officer->id,
                'officer_id' => $officer->officer_id,
            ],
        ]);
    }
}
