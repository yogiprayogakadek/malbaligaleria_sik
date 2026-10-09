<?php

namespace App\Http\Controllers;

use App\Models\WorkPermit;
use App\Services\PermitPdfService;
use App\Services\WorkPermitWorkflowNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class WorkPermitApplicantController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $permit = $this->permit($request, $token)->load('statusLogs');

        return response()
            ->view('portal.permits.work.status', compact('permit'))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function uploadPaymentProof(Request $request, string $token, WorkPermitWorkflowNotifier $notifier): RedirectResponse
    {
        $this->permit($request, $token);

        $request->validate([
            'payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $path = $request->file('payment_proof')->store('permits/work/payment-proofs', 'local');
        $oldPath = null;

        try {
            $permit = DB::transaction(function () use ($request, $token, $path, &$oldPath): WorkPermit {
                $permit = WorkPermit::where('applicant_token', $token)->lockForUpdate()->firstOrFail();
                abort_unless(in_array($permit->status, ['awaiting_payment', 'payment_revision'], true), 409, 'Bukti pembayaran tidak dapat diubah pada status ini.');
                $oldPath = $permit->payment_proof_path;
                $from = $permit->status;
                $permit->update([
                    'status' => 'payment_review',
                    'payment_proof_path' => $path,
                    'payment_proof_type' => $request->file('payment_proof')->getMimeType(),
                    'payment_submitted_at' => now(),
                    'payment_verified_by' => null,
                    'payment_verified_at' => null,
                ]);
                $permit->statusLogs()->create([
                    'actor_id' => $request->user()?->id,
                    'from_status' => $from,
                    'to_status' => 'payment_review',
                    'action' => 'payment_proof_uploaded',
                ]);

                return $permit;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('local')->delete($oldPath);
        }

        $notifier->notifyDivision(
            $permit,
            'FIN',
            'Bukti Deposit Baru',
            "Bukti pembayaran #{$permit->permit_number} menunggu verifikasi Finance.",
            'finance',
            WorkPermit::where('assigned_division', 'MEP')->where('status', 'payment_review')->count(),
        );

        return back()->with('success', 'Bukti pembayaran berhasil dikirim ke Finance.');
    }

    public function refundProof(Request $request, string $token): StreamedResponse
    {
        $permit = $this->permit($request, $token);
        abort_unless($permit->refund_proof_path && Storage::disk('local')->exists($permit->refund_proof_path), 404);

        $response = Storage::disk('local')->response($permit->refund_proof_path);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public function previewRefundProof(Request $request, string $token): View
    {
        $permit = $this->permit($request, $token);
        abort_unless($permit->refund_proof_path && Storage::disk('local')->exists($permit->refund_proof_path), 404);

        return view('documents.preview', [
            'title' => 'Bukti Pengembalian Deposit',
            'reference' => $permit->permit_number,
            'sourceUrl' => URL::temporarySignedRoute('work-permits.refund-proof', now()->addMinutes(10), [
                'token' => $permit->applicant_token,
            ]),
            'downloadUrl' => null,
            'backUrl' => route('work-permits.status', $permit->applicant_token),
            'isImage' => str_starts_with((string) Storage::disk('local')->mimeType($permit->refund_proof_path), 'image/'),
        ]);
    }

    public function downloadLetter(Request $request, string $token, PermitPdfService $pdf): Response
    {
        $permit = $this->permit($request, $token)->load(['workers', 'reviewer']);

        abort_unless(in_array($permit->status, ['approved', 'completed', 'refund_processing', 'refunded'], true), 404);

        return $pdf->workPermit($permit);
    }

    public function inlineLetter(Request $request, string $token, PermitPdfService $pdf): Response
    {
        $permit = $this->permit($request, $token)->load(['workers', 'reviewer']);
        abort_unless(in_array($permit->status, ['approved', 'completed', 'refund_processing', 'refunded'], true), 404);

        return $pdf->workPermitInline($permit);
    }

    public function previewLetter(Request $request, string $token): View
    {
        $permit = $this->permit($request, $token);
        abort_unless(in_array($permit->status, ['approved', 'completed', 'refund_processing', 'refunded'], true), 404);

        return view('documents.preview', [
            'title' => 'Surat Izin Kerja',
            'reference' => $permit->permit_number,
            'sourceUrl' => route('work-permits.letter.inline', $permit->applicant_token),
            'downloadUrl' => route('work-permits.letter', $permit->applicant_token),
            'backUrl' => route('work-permits.status', $permit->applicant_token),
            'isImage' => false,
        ]);
    }

    private function permit(Request $request, string $token): WorkPermit
    {
        $permit = WorkPermit::where('applicant_token', $token)->firstOrFail();

        if ($permit->user_id !== null) {
            abort_unless($request->user()?->id === $permit->user_id, 403);
        }

        return $permit;
    }
}
