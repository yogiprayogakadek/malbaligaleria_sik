<?php

namespace App\Http\Controllers;

use App\Exceptions\OfficialDocumentIntegrityException;
use App\Models\LoadingPermit;
use App\Models\WorkPermit;
use App\Services\PermitPdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ApprovalVerificationController extends Controller
{
    public function __invoke(string $documentId, PermitPdfService $pdf): Response
    {
        abort_unless(Str::isUuid($documentId), 404);

        $permit = LoadingPermit::query()
            ->with('reviewer')
            ->where('official_document_id', $documentId)
            ->first();
        $permitType = 'loading';

        if (! $permit) {
            $permit = WorkPermit::query()
                ->with('reviewer')
                ->where('official_document_id', $documentId)
                ->first();
            $permitType = $permit?->isEventPermit() ? 'event' : 'work';
        }

        abort_unless($permit, 404);

        $integrityValid = true;
        try {
            $permit instanceof LoadingPermit
                ? $pdf->assertLoadingPermitIntegrity($permit)
                : $pdf->assertWorkPermitIntegrity($permit);
        } catch (OfficialDocumentIntegrityException) {
            $integrityValid = false;
        }

        $revoked = $permit->revoked_at !== null;
        $valid = $integrityValid && ! $revoked;
        $division = $permit->official_approved_by_division ?: match ($permitType) {
            'loading' => 'Tenant Relationship',
            'event' => 'Event Promotion',
            default => $permit->assigned_division === 'MEP' ? 'Engineering' : (string) $permit->assigned_division,
        };

        $response = response()->view('documents.approval-verification', [
            'permit' => $permit,
            'permitType' => $permitType,
            'permitTypeLabel' => match ($permitType) {
                'loading' => 'Loading / Unloading',
                'event' => 'Surat Izin Event',
                default => 'Surat Izin Kerja',
            },
            'validatorName' => $permit->official_approved_by_name ?: ($permit->reviewer?->name ?: 'Validator '.$division),
            'division' => $division,
            'valid' => $valid,
            'integrityValid' => $integrityValid,
            'revoked' => $revoked,
        ], $valid ? 200 : 409);

        return $response
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
