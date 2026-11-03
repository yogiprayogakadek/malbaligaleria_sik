<?php

namespace App\Http\Controllers;

use App\Models\WorkPermit;
use App\Services\PermitCommunicationTimeline;
use App\Services\PermitPdfService;
use App\Services\SensitiveDocumentResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class StaffWorkPermitController extends Controller
{
    public function show(Request $request, string $token, PermitCommunicationTimeline $timeline): View
    {
        $permit = WorkPermit::query()
            ->where('public_token', $token)
            ->with('workers', 'revisions', 'revoker', 'documentVersions.replacer')
            ->firstOrFail();
        $this->authorizeStaff($request, $permit);

        $communicationTimeline = $timeline->forWork($permit);
        $activeValidatorDivision = $this->activeDivision($request, $permit);

        return view('staff.work-permits.show', compact('permit', 'communicationTimeline', 'activeValidatorDivision'));
    }

    public function document(Request $request, string $token, SensitiveDocumentResponse $documents): Response
    {
        $permit = WorkPermit::query()->where('public_token', $token)->firstOrFail();
        $this->authorizeStaff($request, $permit);
        abort_if($request->user()->isSecretary() || $this->isFinanceWorkspace($request, $permit), 403);
        abort_unless($permit->id_doc_path && Storage::disk('local')->exists($permit->id_doc_path), 404);

        return $documents->make($permit->id_doc_path, $request->user(), $permit, 'identity');
    }

    public function previewDocument(Request $request, string $token): View
    {
        $permit = WorkPermit::where('public_token', $token)->firstOrFail();
        $this->authorizeStaff($request, $permit);
        abort_if($request->user()->isSecretary() || $this->isFinanceWorkspace($request, $permit), 403);

        return $this->filePreview($request, $permit, $permit->id_doc_path, 'Dokumen Identitas Tenant', 'staff.work-permits.document');
    }

    public function assignmentLetter(Request $request, string $token, SensitiveDocumentResponse $documents): Response
    {
        $permit = WorkPermit::query()->where('public_token', $token)->firstOrFail();
        $this->authorizeStaff($request, $permit);
        abort_if($request->user()->isSecretary() || $this->isFinanceWorkspace($request, $permit), 403);
        abort_unless($permit->assignment_letter_path && Storage::disk('local')->exists($permit->assignment_letter_path), 404);

        return $documents->make($permit->assignment_letter_path, $request->user(), $permit, 'assignment_letter');
    }

    public function previewAssignmentLetter(Request $request, string $token): View
    {
        $permit = WorkPermit::query()->where('public_token', $token)->firstOrFail();
        $this->authorizeStaff($request, $permit);
        abort_if($request->user()->isSecretary() || $this->isFinanceWorkspace($request, $permit), 403);

        return $this->filePreview($request, $permit, $permit->assignment_letter_path, 'Surat Tugas', 'staff.work-permits.assignment-letter');
    }

    public function letter(Request $request, string $token, PermitPdfService $pdf): Response
    {
        $permit = WorkPermit::query()
            ->where('public_token', $token)
            ->with(['workers', 'reviewer'])
            ->firstOrFail();
        $this->authorizeStaff($request, $permit);

        abort_if($this->isFinanceWorkspace($request, $permit), 403);
        abort_if($permit->revoked_at, 404);
        abort_unless(in_array($permit->status, ['approved', 'completed', 'refund_processing', 'refunded'], true), 404);

        return $pdf->workPermit($permit);
    }

    public function inlineLetter(Request $request, string $token, PermitPdfService $pdf): Response
    {
        $permit = $this->approvedLetterPermit($request, $token);

        return $pdf->workPermitInline($permit);
    }

    public function previewLetter(Request $request, string $token): View
    {
        $permit = $this->approvedLetterPermit($request, $token);

        return view('documents.preview', [
            'title' => $permit->permit_title,
            'reference' => $permit->permit_number,
            'sourceUrl' => route('staff.work-permits.letter.inline', $permit->public_token),
            'downloadUrl' => route('staff.work-permits.letter', $permit->public_token),
            'backUrl' => route('staff.work-permits.show', $request->user()->workPermitRouteParameters($permit)),
            'isImage' => false,
        ]);
    }

    public function paymentProof(Request $request, string $token, SensitiveDocumentResponse $documents): Response
    {
        $permit = WorkPermit::where('public_token', $token)->firstOrFail();
        $this->authorizeStaff($request, $permit);
        abort_if($request->user()->isSecretary(), 403);
        abort_unless($permit->assigned_division === 'MEP', 403);
        abort_unless($permit->payment_proof_path && Storage::disk('local')->exists($permit->payment_proof_path), 404);

        return $documents->make($permit->payment_proof_path, $request->user(), $permit, 'payment_proof');
    }

    public function previewPaymentProof(Request $request, string $token): View
    {
        $permit = WorkPermit::where('public_token', $token)->firstOrFail();
        $this->authorizeStaff($request, $permit);
        abort_if($request->user()->isSecretary(), 403);
        abort_unless($permit->assigned_division === 'MEP', 403);

        return $this->filePreview($request, $permit, $permit->payment_proof_path, 'Bukti Pembayaran Deposit', 'staff.work-permits.payment-proof');
    }

    public function refundProof(Request $request, string $token, SensitiveDocumentResponse $documents): Response
    {
        $permit = WorkPermit::where('public_token', $token)->firstOrFail();
        $this->authorizeStaff($request, $permit);
        abort_if($request->user()->isSecretary(), 403);
        abort_unless($permit->assigned_division === 'MEP', 403);
        abort_if($this->isFinanceWorkspace($request, $permit), 403);
        abort_unless($permit->refund_proof_path && Storage::disk('local')->exists($permit->refund_proof_path), 404);

        return $documents->make($permit->refund_proof_path, $request->user(), $permit, 'refund_proof');
    }

    public function previewRefundProof(Request $request, string $token): View
    {
        $permit = WorkPermit::where('public_token', $token)->firstOrFail();
        $this->authorizeStaff($request, $permit);
        abort_if($request->user()->isSecretary(), 403);
        abort_unless($permit->assigned_division === 'MEP', 403);
        abort_if($this->isFinanceWorkspace($request, $permit), 403);

        return $this->filePreview($request, $permit, $permit->refund_proof_path, 'Bukti Pengembalian Deposit', 'staff.work-permits.refund-proof');
    }

    private function authorizeStaff(Request $request, WorkPermit $permit): void
    {
        $user = $request->user();
        abort_unless($user?->is_active, 403);

        if ($user->isAdmin() || $user->isSecretary()) {
            return;
        }

        $isAssignedValidator = $user->canValidateDivision($permit->assigned_division);
        $isFinanceReviewer = $user->isValidator()
            && $user->canValidateDivision('FIN')
            && $permit->assigned_division === 'MEP'
            && $permit->deposit_required;

        abort_unless($isAssignedValidator || $isFinanceReviewer, 403);
    }

    private function activeDivision(Request $request, WorkPermit $permit): ?string
    {
        $user = $request->user();
        if (! $user->isValidator()) {
            return null;
        }

        $requested = strtoupper((string) $request->query('workspace'));
        $requestedMatchesPermit = $requested === $permit->assigned_division
            || ($requested === 'FIN' && $permit->assigned_division === 'MEP' && $permit->deposit_required);
        if ($requestedMatchesPermit && $user->canValidateDivision($requested)) {
            return $requested;
        }
        if ($user->canValidateDivision($permit->assigned_division)) {
            return $permit->assigned_division;
        }
        if ($permit->assigned_division === 'MEP' && $user->canValidateDivision('FIN')) {
            return 'FIN';
        }

        return $user->division;
    }

    private function approvedLetterPermit(Request $request, string $token): WorkPermit
    {
        $permit = WorkPermit::query()
            ->where('public_token', $token)
            ->with(['workers', 'reviewer'])
            ->firstOrFail();
        $this->authorizeStaff($request, $permit);
        abort_if($this->isFinanceWorkspace($request, $permit), 403);
        abort_if($permit->revoked_at, 404);
        abort_unless(in_array($permit->status, ['approved', 'completed', 'refund_processing', 'refunded'], true), 404);

        return $permit;
    }

    private function filePreview(Request $request, WorkPermit $permit, ?string $path, string $title, string $rawRoute): View
    {
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return view('documents.preview', [
            'title' => $title,
            'reference' => $permit->permit_number,
            'sourceUrl' => URL::temporarySignedRoute($rawRoute, now()->addMinutes(5), [
                'token' => $permit->public_token,
            ]),
            'downloadUrl' => null,
            'backUrl' => route('staff.work-permits.show', $request->user()->workPermitRouteParameters($permit)),
            'isImage' => str_starts_with((string) Storage::disk('local')->mimeType($path), 'image/'),
        ]);
    }

    private function isFinanceWorkspace(Request $request, WorkPermit $permit): bool
    {
        return $request->user()->isValidator() && $this->activeDivision($request, $permit) === 'FIN';
    }
}
