<?php

namespace App\Http\Controllers;

use App\Models\User;
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
            ->with('workers', 'assignee')
            ->firstOrFail();
        $this->authorizeStaff($request, $permit);

        $eligibleValidators = collect();
        if ($request->user()->isAdmin() && $permit->actionDivision()) {
            $eligibleValidators = User::query()
                ->where('role', 'validator')
                ->where('division', $permit->actionDivision())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);
        }
        $communicationTimeline = $timeline->forWork($permit);

        return view('staff.work-permits.show', compact('permit', 'eligibleValidators', 'communicationTimeline'));
    }

    public function document(Request $request, string $token, SensitiveDocumentResponse $documents): Response
    {
        $permit = WorkPermit::query()->where('public_token', $token)->firstOrFail();
        $this->authorizeStaff($request, $permit);
        abort_if($request->user()->isSecretary() || $request->user()->division === 'FIN', 403);
        abort_unless($permit->id_doc_path && Storage::disk('local')->exists($permit->id_doc_path), 404);

        return $documents->make($permit->id_doc_path, $request->user(), $permit, 'identity');
    }

    public function previewDocument(Request $request, string $token): View
    {
        $permit = WorkPermit::where('public_token', $token)->firstOrFail();
        $this->authorizeStaff($request, $permit);
        abort_if($request->user()->isSecretary() || $request->user()->division === 'FIN', 403);

        return $this->filePreview($permit, $permit->id_doc_path, 'Dokumen Identitas Tenant', 'staff.work-permits.document');
    }

    public function assignmentLetter(Request $request, string $token, SensitiveDocumentResponse $documents): Response
    {
        $permit = WorkPermit::query()->where('public_token', $token)->firstOrFail();
        $this->authorizeStaff($request, $permit);
        abort_if($request->user()->isSecretary() || $request->user()->division === 'FIN', 403);
        abort_unless($permit->assignment_letter_path && Storage::disk('local')->exists($permit->assignment_letter_path), 404);

        return $documents->make($permit->assignment_letter_path, $request->user(), $permit, 'assignment_letter');
    }

    public function previewAssignmentLetter(Request $request, string $token): View
    {
        $permit = WorkPermit::query()->where('public_token', $token)->firstOrFail();
        $this->authorizeStaff($request, $permit);
        abort_if($request->user()->isSecretary() || $request->user()->division === 'FIN', 403);

        return $this->filePreview($permit, $permit->assignment_letter_path, 'Surat Tugas', 'staff.work-permits.assignment-letter');
    }

    public function letter(Request $request, string $token, PermitPdfService $pdf): Response
    {
        $permit = WorkPermit::query()
            ->where('public_token', $token)
            ->with(['workers', 'reviewer'])
            ->firstOrFail();
        $this->authorizeStaff($request, $permit);

        abort_if($request->user()->isValidator() && $request->user()->division === 'FIN', 403);
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
            'title' => 'Surat Izin Kerja',
            'reference' => $permit->permit_number,
            'sourceUrl' => route('staff.work-permits.letter.inline', $permit->public_token),
            'downloadUrl' => route('staff.work-permits.letter', $permit->public_token),
            'backUrl' => route('staff.work-permits.show', $permit->public_token),
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

        return $this->filePreview($permit, $permit->payment_proof_path, 'Bukti Pembayaran Deposit', 'staff.work-permits.payment-proof');
    }

    public function refundProof(Request $request, string $token, SensitiveDocumentResponse $documents): Response
    {
        $permit = WorkPermit::where('public_token', $token)->firstOrFail();
        $this->authorizeStaff($request, $permit);
        abort_if($request->user()->isSecretary(), 403);
        abort_unless($permit->assigned_division === 'MEP', 403);
        abort_if($request->user()->isValidator() && $request->user()->division === 'FIN', 403);
        abort_unless($permit->refund_proof_path && Storage::disk('local')->exists($permit->refund_proof_path), 404);

        return $documents->make($permit->refund_proof_path, $request->user(), $permit, 'refund_proof');
    }

    public function previewRefundProof(Request $request, string $token): View
    {
        $permit = WorkPermit::where('public_token', $token)->firstOrFail();
        $this->authorizeStaff($request, $permit);
        abort_if($request->user()->isSecretary(), 403);
        abort_unless($permit->assigned_division === 'MEP', 403);
        abort_if($request->user()->isValidator() && $request->user()->division === 'FIN', 403);

        return $this->filePreview($permit, $permit->refund_proof_path, 'Bukti Pengembalian Deposit', 'staff.work-permits.refund-proof');
    }

    private function authorizeStaff(Request $request, WorkPermit $permit): void
    {
        $user = $request->user();
        abort_unless($user?->is_active, 403);

        if ($user->isAdmin() || $user->isSecretary()) {
            return;
        }

        $isAssignedValidator = $user->isValidator() && $user->division === $permit->assigned_division;
        $isFinanceReviewer = $user->isValidator()
            && $user->division === 'FIN'
            && $permit->assigned_division === 'MEP'
            && $permit->deposit_required;

        abort_unless($isAssignedValidator || $isFinanceReviewer, 403);
    }

    private function approvedLetterPermit(Request $request, string $token): WorkPermit
    {
        $permit = WorkPermit::query()
            ->where('public_token', $token)
            ->with(['workers', 'reviewer'])
            ->firstOrFail();
        $this->authorizeStaff($request, $permit);
        abort_if($request->user()->isValidator() && $request->user()->division === 'FIN', 403);
        abort_unless(in_array($permit->status, ['approved', 'completed', 'refund_processing', 'refunded'], true), 404);

        return $permit;
    }

    private function filePreview(WorkPermit $permit, ?string $path, string $title, string $rawRoute): View
    {
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return view('documents.preview', [
            'title' => $title,
            'reference' => $permit->permit_number,
            'sourceUrl' => URL::temporarySignedRoute($rawRoute, now()->addMinutes(5), [
                'token' => $permit->public_token,
            ]),
            'downloadUrl' => null,
            'backUrl' => route('staff.work-permits.show', $permit->public_token),
            'isImage' => str_starts_with((string) Storage::disk('local')->mimeType($path), 'image/'),
        ]);
    }
}
