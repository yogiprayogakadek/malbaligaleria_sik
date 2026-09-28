<?php

namespace App\Http\Controllers;

use App\Models\PermitNotification;
use App\Models\WorkPermit;
use App\Services\WorkPermitWorkflowNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['all', 'payment_review', 'payment_revision', 'verified'], true)
            ? $request->query('status')
            : 'payment_review';

        $query = WorkPermit::query()->where('assigned_division', 'MEP')->where('deposit_required', true)->latest('payment_submitted_at');
        if ($status === 'verified') {
            $query->whereNotNull('payment_verified_at');
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        return view('finance.index', [
            'permits' => $query->paginate(15)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function verify(Request $request, string $token, WorkPermitWorkflowNotifier $notifier): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:verified,revision'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $permit = DB::transaction(function () use ($request, $token, $data): WorkPermit {
            $permit = WorkPermit::where('public_token', $token)->lockForUpdate()->firstOrFail();
            abort_unless($permit->assigned_division === 'MEP' && $permit->status === 'payment_review', 409, 'Bukti pembayaran sudah diproses.');

            $from = $permit->status;
            $verified = $data['decision'] === 'verified';
            $permit->update([
                'status' => $verified ? 'mep_final_review' : 'payment_revision',
                'payment_verified_by' => $verified ? $request->user()->id : null,
                'payment_verified_at' => $verified ? now() : null,
                'finance_notes' => $data['notes'],
            ]);
            $permit->statusLogs()->create([
                'actor_id' => $request->user()->id,
                'from_status' => $from,
                'to_status' => $permit->status,
                'action' => $verified ? 'payment_verified' : 'payment_revision_requested',
                'notes' => $data['notes'],
            ]);

            return $permit;
        });

        if ($data['decision'] === 'verified') {
            $notifier->notifyDivision(
                $permit,
                'MEP',
                'Deposit Terverifikasi',
                "Finance telah memverifikasi deposit #{$permit->permit_number}.",
                'work',
                WorkPermit::needsMepAction()->count(),
            );
            $notifier->notifyApplicant($permit, 'Pembayaran Terverifikasi', 'Security deposit telah diterima dan permohonan kembali diperiksa oleh MEP.');
        } else {
            $notifier->notifyApplicant($permit, 'Bukti Pembayaran Perlu Diperbaiki', $data['notes'] ?: 'Finance meminta Anda mengunggah ulang bukti pembayaran.');
        }

        return redirect()->route('finance.index')->with('success', 'Status pembayaran berhasil diperbarui.');
    }

    public function readNotification(PermitNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === request()->user()->id, 403);
        abort_unless($notification->work_permit_id !== null, 404);
        $notification->markRead();

        return redirect()->route('staff.work-permits.show', $notification->workPermit->public_token);
    }
}
