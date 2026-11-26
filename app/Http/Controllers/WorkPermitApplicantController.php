<?php

namespace App\Http\Controllers;

use App\Contracts\MalwareScanner;
use App\Models\WorkPermit;
use App\Services\DepositInvoiceService;
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
    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $permit = $this->permit($request, $token)->load(['statusLogs', 'revisions']);

        if ($request->user()?->isStaff()) {
            return redirect()->route('staff.work-permits.show', $permit->public_token);
        }

        $letterPreviewUrl = ! $permit->revoked_at && in_array($permit->status, ['approved', 'completed', 'refund_processing', 'refunded'], true)
            ? URL::temporarySignedRoute('work-permits.letter.preview', now()->addDays(30), ['token' => $permit->applicant_token])
            : null;

        return response()
            ->view('portal.permits.work.status', compact('permit', 'letterPreviewUrl'))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function uploadPaymentProof(Request $request, string $token, WorkPermitWorkflowNotifier $notifier, MalwareScanner $malwareScanner): RedirectResponse
    {
        $this->permit($request, $token);

        $request->validate([
            'payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ], [
            'payment_proof.uploaded' => 'Bukti transfer gagal diunggah. Coba gunakan foto yang lebih kecil atau pilih dari galeri.',
            'payment_proof.mimes' => 'Bukti transfer harus berupa PDF, JPG, PNG, atau WebP.',
            'payment_proof.max' => 'Ukuran bukti transfer maksimal 8 MB.',
        ]);
        $malwareScanner->scan($request->file('payment_proof'), 'payment_proof');
        $path = $request->file('payment_proof')->store('permits/work/payment-proofs', 'local');
        $oldPath = null;

        try {
            $permit = DB::transaction(function () use ($request, $token, $path, &$oldPath): WorkPermit {
                $permit = WorkPermit::where('applicant_token', $token)->lockForUpdate()->firstOrFail();
                abort_unless(in_array($permit->status, ['awaiting_payment', 'payment_revision'], true), 409, 'Bukti pembayaran tidak dapat diubah pada status ini.');
                abort_unless($permit->deposit_invoice_path && Storage::disk('local')->exists($permit->deposit_invoice_path), 409, 'Invoice deposit belum tersedia.');
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
        $notifier->notifyStaffWhatsapp($permit, 'payment_proof_uploaded_'.$permit->updated_at->timestamp, 'Bukti Deposit Baru', "Bukti pembayaran #{$permit->permit_number} menunggu verifikasi Finance.");
        $notifier->notifyApplicant(
            $permit,
            'Bukti Pembayaran Diterima',
            'Bukti pembayaran telah diterima dan menunggu pemeriksaan Finance.',
        );

        return back()->with('success', 'Bukti pembayaran berhasil dikirim ke Finance.');
    }

    public function refundProof(Request $request, string $token): StreamedResponse
    {
        $permit = $this->permit($request, $token);
        abort_unless($permit->refund_proof_path && Storage::disk('local')->exists($permit->refund_proof_path), 404);
        $extension = match (Storage::disk('local')->mimeType($permit->refund_proof_path)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'pdf',
        };
        $response = $request->boolean('download')
            ? Storage::disk('local')->download($permit->refund_proof_path, "bukti-pengembalian-deposit.{$extension}")
            : Storage::disk('local')->response($permit->refund_proof_path);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public function depositInvoice(Request $request, string $token, DepositInvoiceService $invoices): StreamedResponse
    {
        $permit = $this->permit($request, $token);
        abort_unless($permit->deposit_invoice_path && Storage::disk('local')->exists($permit->deposit_invoice_path), 404);
        $invoices->assertIntegrity($permit);
        $extension = match (Storage::disk('local')->mimeType($permit->deposit_invoice_path)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'pdf',
        };
        $response = $request->boolean('download')
            ? Storage::disk('local')->download($permit->deposit_invoice_path, "invoice-deposit.{$extension}")
            : Storage::disk('local')->response($permit->deposit_invoice_path);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public function previewDepositInvoice(Request $request, string $token, DepositInvoiceService $invoices): View
    {
        $permit = $this->permit($request, $token);
        abort_unless($permit->deposit_invoice_path && Storage::disk('local')->exists($permit->deposit_invoice_path), 404);
        $invoices->assertIntegrity($permit);
        $sourceUrl = URL::temporarySignedRoute('work-permits.deposit-invoice', now()->addMinutes(10), [
            'token' => $permit->applicant_token,
        ]);
        $downloadUrl = URL::temporarySignedRoute('work-permits.deposit-invoice', now()->addMinutes(10), [
            'token' => $permit->applicant_token,
            'download' => 1,
        ]);

        return view('documents.preview', [
            'title' => 'Invoice Security Deposit',
            'reference' => $permit->permit_number,
            'sourceUrl' => $sourceUrl,
            'openUrl' => $sourceUrl,
            'downloadUrl' => $downloadUrl,
            'backUrl' => route('work-permits.status', $permit->applicant_token),
            'isImage' => str_starts_with((string) Storage::disk('local')->mimeType($permit->deposit_invoice_path), 'image/'),
        ]);
    }

    public function previewRefundProof(Request $request, string $token): View
    {
        $permit = $this->permit($request, $token);
        abort_unless($permit->refund_proof_path && Storage::disk('local')->exists($permit->refund_proof_path), 404);
        $isImage = str_starts_with((string) Storage::disk('local')->mimeType($permit->refund_proof_path), 'image/');
        $sourceUrl = URL::temporarySignedRoute('work-permits.refund-proof', now()->addMinutes(10), [
            'token' => $permit->applicant_token,
        ]);
        $downloadUrl = ! $isImage
            ? URL::temporarySignedRoute('work-permits.refund-proof', now()->addMinutes(10), [
                'token' => $permit->applicant_token,
                'download' => 1,
            ])
            : null;

        return view('documents.preview', [
            'title' => 'Bukti Pengembalian Deposit',
            'reference' => $permit->permit_number,
            'sourceUrl' => $sourceUrl,
            'openUrl' => ! $isImage ? $sourceUrl : null,
            'downloadUrl' => $downloadUrl,
            'backUrl' => route('work-permits.status', $permit->applicant_token),
            'isImage' => $isImage,
        ]);
    }

    public function downloadLetter(Request $request, string $token, PermitPdfService $pdf): Response
    {
        $permit = $this->permit($request, $token)->load(['workers', 'reviewer']);

        abort_if($permit->revoked_at, 404);
        abort_unless(in_array($permit->status, ['approved', 'completed', 'refund_processing', 'refunded'], true), 404);

        return $pdf->workPermit($permit);
    }

    public function inlineLetter(Request $request, string $token, PermitPdfService $pdf): Response
    {
        $permit = $this->permit($request, $token)->load(['workers', 'reviewer']);
        abort_if($permit->revoked_at, 404);
        abort_unless(in_array($permit->status, ['approved', 'completed', 'refund_processing', 'refunded'], true), 404);

        return $pdf->workPermitInline($permit);
    }

    public function previewLetter(Request $request, string $token): View
    {
        $permit = $this->permit($request, $token);
        abort_if($permit->revoked_at, 404);
        abort_unless(in_array($permit->status, ['approved', 'completed', 'refund_processing', 'refunded'], true), 404);
        $sourceUrl = URL::temporarySignedRoute('work-permits.letter.inline', now()->addMinutes(10), [
            'token' => $permit->applicant_token,
        ]);
        $downloadUrl = URL::temporarySignedRoute('work-permits.letter', now()->addMinutes(10), [
            'token' => $permit->applicant_token,
        ]);

        return view('documents.preview', [
            'title' => 'Surat Izin Kerja',
            'reference' => $permit->permit_number,
            'sourceUrl' => $sourceUrl,
            'openUrl' => $sourceUrl,
            'downloadUrl' => $downloadUrl,
            'backUrl' => route('work-permits.status', $permit->applicant_token),
            'isImage' => false,
        ]);
    }

    private function permit(Request $request, string $token): WorkPermit
    {
        $permit = WorkPermit::where('permit_kind', 'work')->where('applicant_token', $token)->firstOrFail();
        $hasValidSignature = $request->hasValidSignature() || $request->hasValidSignature(false);

        if ($permit->user_id !== null && ! $hasValidSignature) {
            abort_unless($request->user()?->id === $permit->user_id, 403);
        }

        return $permit;
    }
}
