<?php

namespace App\Http\Controllers;

use App\Contracts\MalwareScanner;
use App\Models\PermitNotification;
use App\Models\WorkPermit;
use App\Services\PermitAssignmentService;
use App\Services\PermitPdfService;
use App\Services\WorkPermitWorkflowNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MEPController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['all', 'action', 'revision_required', 'approved', 'completed', 'refunded', 'rejected'], true)
            ? $request->query('status')
            : 'action';

        $query = WorkPermit::query()->where('assigned_division', 'MEP')->withCount('workers')->slaPriority();
        if ($status === 'action') {
            $query->needsMepAction();
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        [$perPage, $perPageRaw] = $this->tablePageSize($request);

        return view('mep.index', [
            'permits' => $query->paginate($perPage)->withQueryString(),
            'perPageRaw' => $perPageRaw,
            'status' => $status,
            'counts' => [
                'all' => WorkPermit::where('assigned_division', 'MEP')->count(),
                'action' => WorkPermit::needsMepAction()->count(),
                'revision_required' => WorkPermit::where('assigned_division', 'MEP')->where('status', 'revision_required')->count(),
                'approved' => WorkPermit::where('assigned_division', 'MEP')->where('status', 'approved')->count(),
                'completed' => WorkPermit::where('assigned_division', 'MEP')->where('status', 'completed')->count(),
                'refunded' => WorkPermit::where('assigned_division', 'MEP')->where('status', 'refunded')->count(),
                'rejected' => WorkPermit::where('assigned_division', 'MEP')->where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function decideDeposit(Request $request, string $token, WorkPermitWorkflowNotifier $notifier, PermitAssignmentService $assignments, PermitPdfService $pdf): RedirectResponse
    {
        $data = $request->validate([
            'deposit_required' => ['required', 'boolean'],
            'deposit_amount' => [Rule::requiredIf($request->boolean('deposit_required')), 'nullable', 'numeric', 'min:1', 'max:999999999999.99'],
            'deposit_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $permit = DB::transaction(function () use ($request, $token, $data, $assignments, $pdf): WorkPermit {
            $permit = WorkPermit::where('public_token', $token)->lockForUpdate()->firstOrFail();
            abort_unless($permit->assigned_division === 'MEP' && $permit->status === 'mep_review', 409, 'Keputusan MEP sudah dibuat.');
            $assignments->ensureWorkAssignee($permit, $request->user());

            $required = $request->boolean('deposit_required');
            $from = $permit->status;
            $permit->update([
                'security_deposit' => $required,
                'deposit_required' => $required,
                'deposit_amount' => $required ? $data['deposit_amount'] : null,
                'deposit_notes' => $data['deposit_notes'] ?? null,
                'deposit_set_by' => $request->user()->id,
                'deposit_set_at' => now(),
                'status' => $required ? 'awaiting_payment' : 'approved',
                'reviewed_by' => $required ? null : $request->user()->id,
                'reviewed_at' => $required ? null : now(),
            ]);
            $permit->statusLogs()->create([
                'actor_id' => $request->user()->id,
                'from_status' => $from,
                'to_status' => $permit->status,
                'action' => $required ? 'deposit_requested' : 'approved_without_deposit',
                'notes' => $data['deposit_notes'] ?? null,
                'metadata' => $required ? ['deposit_amount' => $data['deposit_amount']] : null,
            ]);

            if ($permit->status === 'approved') {
                $pdf->sealWorkPermit($permit);
            }

            return $permit;
        });

        $message = $permit->deposit_required
            ? 'MEP menetapkan security deposit sebesar Rp '.number_format((float) $permit->deposit_amount, 0, ',', '.').'. Silakan unggah bukti pembayaran.'
            : 'Permohonan telah disetujui tanpa security deposit.';
        $notifier->notifyApplicant($permit, $permit->deposit_required ? 'Security Deposit Diperlukan' : 'Permohonan Izin Kerja Disetujui', $message);

        return back()->with('success', 'Keputusan MEP berhasil disimpan. Pembaruan status telah dikirim kepada pemohon.');
    }

    public function finalDecision(Request $request, string $token, WorkPermitWorkflowNotifier $notifier, PermitAssignmentService $assignments, PermitPdfService $pdf): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $permit = DB::transaction(function () use ($request, $token, $data, $assignments, $pdf): WorkPermit {
            $permit = WorkPermit::where('public_token', $token)->lockForUpdate()->firstOrFail();
            abort_unless($permit->assigned_division === 'MEP' && $permit->status === 'mep_final_review', 409, 'Permohonan belum siap untuk keputusan akhir.');
            $assignments->ensureWorkAssignee($permit, $request->user());
            $from = $permit->status;
            $permit->update([
                'status' => $data['decision'],
                'review_notes' => $data['review_notes'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
            $permit->statusLogs()->create([
                'actor_id' => $request->user()->id,
                'from_status' => $from,
                'to_status' => $permit->status,
                'action' => 'mep_final_decision',
                'notes' => $data['review_notes'] ?? null,
            ]);

            if ($permit->status === 'approved') {
                $pdf->sealWorkPermit($permit);
            }

            return $permit;
        });

        $notifier->notifyApplicant(
            $permit,
            $permit->status === 'approved' ? 'Permohonan Izin Kerja Disetujui' : 'Permohonan Izin Kerja Ditolak',
            $permit->status === 'approved' ? 'MEP telah memberikan persetujuan akhir.' : ($permit->review_notes ?: 'Permohonan belum dapat disetujui.'),
        );

        return back()->with('success', 'Keputusan akhir MEP berhasil disimpan. Pembaruan status telah dikirim kepada pemohon.');
    }

    public function complete(Request $request, string $token, WorkPermitWorkflowNotifier $notifier, PermitAssignmentService $assignments): RedirectResponse
    {
        $permit = DB::transaction(function () use ($request, $token, $assignments): WorkPermit {
            $permit = WorkPermit::where('public_token', $token)->lockForUpdate()->firstOrFail();
            abort_unless($permit->assigned_division === 'MEP' && $permit->status === 'approved', 409, 'Pekerjaan belum dapat ditandai selesai.');
            $assignments->ensureWorkAssignee($permit, $request->user());
            $permit->update(['status' => 'completed', 'completed_by' => $request->user()->id, 'completed_at' => now()]);
            $permit->statusLogs()->create([
                'actor_id' => $request->user()->id,
                'from_status' => 'approved',
                'to_status' => 'completed',
                'action' => 'work_completed',
            ]);

            return $permit;
        });

        $notifier->notifyApplicant($permit, 'Pekerjaan Ditandai Selesai', $permit->deposit_required ? 'MEP akan memproses pengembalian security deposit.' : 'Proses izin kerja telah selesai.');

        return back()->with('success', 'Pekerjaan telah ditandai selesai.');
    }

    public function startRefund(Request $request, string $token, WorkPermitWorkflowNotifier $notifier, PermitAssignmentService $assignments): RedirectResponse
    {
        $permit = DB::transaction(function () use ($request, $token, $assignments): WorkPermit {
            $permit = WorkPermit::where('public_token', $token)->lockForUpdate()->firstOrFail();
            abort_unless($permit->assigned_division === 'MEP' && $permit->status === 'completed' && $permit->deposit_required && $permit->payment_verified_at, 409);
            $assignments->ensureWorkAssignee($permit, $request->user());
            $permit->update(['status' => 'refund_processing']);
            $permit->statusLogs()->create([
                'actor_id' => $request->user()->id,
                'from_status' => 'completed',
                'to_status' => 'refund_processing',
                'action' => 'refund_started',
            ]);

            return $permit;
        });

        $notifier->notifyApplicant(
            $permit,
            'Pengembalian Deposit Diproses',
            'MEP sedang memproses pengembalian security deposit.',
        );

        return back()->with('success', 'Proses pengembalian deposit dimulai.');
    }

    public function finishRefund(Request $request, string $token, WorkPermitWorkflowNotifier $notifier, PermitAssignmentService $assignments, MalwareScanner $malwareScanner): RedirectResponse
    {
        $data = $request->validate([
            'refund_amount' => ['required', 'numeric', 'min:1', 'max:999999999999.99'],
            'refund_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'refund_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $malwareScanner->scan($request->file('refund_proof'), 'refund_proof');
        $path = $request->file('refund_proof')->store('permits/work/refund-proofs', 'local');

        try {
            $permit = DB::transaction(function () use ($request, $token, $data, $path, $assignments): WorkPermit {
                $permit = WorkPermit::where('public_token', $token)->lockForUpdate()->firstOrFail();
                abort_unless($permit->assigned_division === 'MEP' && $permit->status === 'refund_processing', 409);
                $assignments->ensureWorkAssignee($permit, $request->user());
                abort_if((float) $data['refund_amount'] > (float) $permit->deposit_amount, 422, 'Nominal pengembalian melebihi deposit.');
                $permit->update([
                    'status' => 'refunded',
                    'refund_amount' => $data['refund_amount'],
                    'refund_proof_path' => $path,
                    'refund_proof_type' => $request->file('refund_proof')->getMimeType(),
                    'refund_notes' => $data['refund_notes'] ?? null,
                    'refund_processed_by' => $request->user()->id,
                    'refund_processed_at' => now(),
                ]);
                $permit->statusLogs()->create([
                    'actor_id' => $request->user()->id,
                    'from_status' => 'refund_processing',
                    'to_status' => 'refunded',
                    'action' => 'refund_completed',
                    'notes' => $data['refund_notes'] ?? null,
                    'metadata' => ['refund_amount' => $data['refund_amount']],
                ]);

                return $permit;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        $notifier->notifyApplicant($permit, 'Security Deposit Dikembalikan', 'Pengembalian security deposit telah diproses oleh MEP. Bukti pengembalian tersedia pada halaman status.');

        return back()->with('success', 'Pengembalian deposit berhasil dicatat.');
    }

    public function readNotification(PermitNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === request()->user()->id, 403);
        abort_unless($notification->work_permit_id !== null, 404);

        $notification->markRead();

        return redirect()->route('staff.work-permits.show', $notification->workPermit->public_token);
    }

    /** @return array{int, string|int} */
    private function tablePageSize(Request $request): array
    {
        $raw = $request->query('per_page', 10);
        $allowed = [5, 10, 20, 50, 100];

        if ($raw === 'all') {
            return [max(1, WorkPermit::where('assigned_division', 'MEP')->count()), 'all'];
        }

        $perPage = in_array((int) $raw, $allowed, true) ? (int) $raw : 10;

        return [$perPage, $perPage];
    }
}
