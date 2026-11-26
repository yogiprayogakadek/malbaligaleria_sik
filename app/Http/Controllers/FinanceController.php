<?php

namespace App\Http\Controllers;

use App\Contracts\MalwareScanner;
use App\Models\PermitNotification;
use App\Models\WorkPermit;
use App\Services\DepositInvoiceService;
use App\Services\PermitAssignmentService;
use App\Services\PermitPdfService;
use App\Services\WorkPermitWorkflowNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FinanceController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['all', 'action', 'awaiting_invoice', 'payment_review', 'payment_revision', 'verified'], true)
            ? $request->query('status')
            : 'action';

        $query = WorkPermit::query()->where('assigned_division', 'MEP')->where('deposit_required', true)->slaPriority();
        if ($status === 'verified') {
            $query->whereNotNull('payment_verified_at');
        } elseif ($status === 'action') {
            $query->whereIn('status', ['awaiting_invoice', 'payment_review']);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $perPageRaw = $request->query('per_page', 10);
        $perPage = $perPageRaw === 'all'
            ? max(1, WorkPermit::where('assigned_division', 'MEP')->where('deposit_required', true)->count())
            : (in_array((int) $perPageRaw, [5, 10, 20, 50, 100], true) ? (int) $perPageRaw : 10);

        return view('finance.index', [
            'permits' => $query->paginate($perPage)->withQueryString(),
            'perPageRaw' => $perPageRaw === 'all' ? 'all' : $perPage,
            'status' => $status,
        ]);
    }

    public function uploadInvoice(
        Request $request,
        string $token,
        WorkPermitWorkflowNotifier $notifier,
        PermitAssignmentService $assignments,
        MalwareScanner $malwareScanner,
        DepositInvoiceService $invoices,
    ): RedirectResponse {
        $request->validate([
            'deposit_invoice' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'deposit_invoice.uploaded' => 'Invoice gagal diunggah. Gunakan file yang lebih kecil lalu coba kembali.',
            'deposit_invoice.mimes' => 'Invoice harus berupa PDF, JPG, PNG, atau WebP.',
            'deposit_invoice.max' => 'Ukuran invoice maksimal 8 MB.',
        ]);

        $invoice = $request->file('deposit_invoice');
        $malwareScanner->scan($invoice, 'deposit_invoice');
        $path = $invoice->store('permits/work/deposit-invoices', 'local');
        $oldPath = null;

        try {
            [$permit, $replaced] = DB::transaction(function () use ($request, $token, $invoice, $path, $assignments, $invoices, &$oldPath): array {
                $permit = WorkPermit::where('public_token', $token)->lockForUpdate()->firstOrFail();
                abort_unless(
                    $permit->assigned_division === 'MEP'
                    && $permit->deposit_required
                    && in_array($permit->status, ['awaiting_invoice', 'awaiting_payment'], true),
                    409,
                    'Invoice tidak dapat diubah pada tahap ini.',
                );
                $assignments->ensureWorkAssignee($permit, $request->user());
                $oldPath = $permit->deposit_invoice_path;
                $from = $permit->status;
                $replaced = filled($oldPath);
                $invoices->registerUpload($permit, $path, $invoice->getMimeType(), $request->user()->id);
                $permit->refresh();
                $permit->statusLogs()->create([
                    'actor_id' => $request->user()->id,
                    'from_status' => $from,
                    'to_status' => 'awaiting_payment',
                    'action' => $replaced ? 'deposit_invoice_replaced' : 'deposit_invoice_uploaded',
                    'metadata' => ['deposit_amount' => $permit->deposit_amount, 'invoice_number' => $permit->deposit_invoice_number],
                ]);

                return [$permit->fresh(), $replaced];
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('local')->delete($oldPath);
        }

        $notifier->notifyApplicant(
            $permit,
            $replaced ? 'Invoice Security Deposit Diperbarui' : 'Invoice Security Deposit Tersedia',
            'Invoice security deposit sebesar Rp '.number_format((float) $permit->deposit_amount, 0, ',', '.').' tersedia pada halaman status. Periksa invoice lalu unggah bukti pembayaran setelah melakukan pembayaran.',
        );

        return back()->with('success', $replaced ? 'Invoice berhasil diganti dan pemohon telah diberi tahu.' : 'Invoice berhasil dikirim dan pemohon telah diberi tahu.');
    }

    public function verify(
        Request $request,
        string $token,
        WorkPermitWorkflowNotifier $notifier,
        PermitAssignmentService $assignments,
        PermitPdfService $pdf,
    ): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:verified,revision'],
            'notes' => [
                ...($request->input('decision') === 'revision' ? ['required', 'min:10'] : ['nullable']),
                'string',
                'max:1000',
            ],
        ]);

        $permit = DB::transaction(function () use ($request, $token, $data, $assignments, $pdf): WorkPermit {
            $permit = WorkPermit::where('public_token', $token)->lockForUpdate()->firstOrFail();
            abort_unless($permit->assigned_division === 'MEP' && $permit->status === 'payment_review', 409, 'Bukti pembayaran sudah diproses.');
            $assignments->ensureWorkAssignee($permit, $request->user());

            $from = $permit->status;
            $verified = $data['decision'] === 'verified';
            $permit->update([
                'status' => $verified ? 'approved' : 'payment_revision',
                'payment_verified_by' => $verified ? $request->user()->id : null,
                'payment_verified_at' => $verified ? now() : null,
                'finance_notes' => $data['notes'] ?? null,
                'reviewed_by' => $verified ? ($permit->deposit_set_by ?: $permit->reviewed_by ?: $request->user()->id) : $permit->reviewed_by,
                'reviewed_at' => $verified ? now() : $permit->reviewed_at,
            ]);
            $permit->statusLogs()->create([
                'actor_id' => $request->user()->id,
                'from_status' => $from,
                'to_status' => $permit->status,
                'action' => $verified ? 'payment_verified' : 'payment_revision_requested',
                'notes' => $data['notes'] ?? null,
                'metadata' => $verified ? ['permit_issued_automatically' => true] : null,
            ]);

            if ($verified) {
                $pdf->sealWorkPermit($permit);
            }

            return $permit->fresh();
        }, 3);

        if ($data['decision'] === 'verified') {
            $notifier->notifyDivision(
                $permit,
                'MEP',
                'Deposit Terverifikasi, Surat Terbit',
                "Finance telah memverifikasi deposit #{$permit->permit_number}. Surat sudah diterbitkan dan tidak memerlukan keputusan lanjutan.",
                'work',
                WorkPermit::needsMepAction()->count(),
                false,
            );
            $notifier->notifyStaffWhatsapp($permit, 'payment_verified_'.$permit->updated_at->timestamp, 'Deposit Terverifikasi, Surat Terbit', "Finance telah memverifikasi deposit #{$permit->permit_number}. Surat izin sudah diterbitkan otomatis.");
            $notifier->notifyApplicant($permit, 'Pembayaran Terverifikasi dan Surat Terbit', 'Security deposit telah diterima. Surat izin resmi sudah terbit dan dapat diunduh melalui halaman status.', true);
        } else {
            $notifier->notifyApplicant($permit, 'Bukti Pembayaran Perlu Diperbaiki', $data['notes']);
        }

        return redirect()->route('finance.index')->with('success', 'Status pembayaran berhasil diperbarui.');
    }

    public function readNotification(PermitNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === request()->user()->id, 403);
        abort_unless($notification->work_permit_id !== null, 404);
        $workspace = request()->user()->workspaceForWorkPermit($notification->workPermit);
        abort_unless($workspace, 403);
        $notification->markRead();

        return redirect()->route('staff.work-permits.show', [
            'token' => $notification->workPermit->public_token,
            'workspace' => 'FIN',
        ]);
    }
}
