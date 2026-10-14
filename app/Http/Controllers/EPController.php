<?php

namespace App\Http\Controllers;

use App\Models\PermitNotification;
use App\Models\WorkPermit;
use App\Services\PermitAssignmentService;
use App\Services\WorkPermitWorkflowNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EPController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['all', 'ep_review', 'approved', 'rejected'], true)
            ? $request->query('status')
            : 'ep_review';

        $query = WorkPermit::query()
            ->where('assigned_division', 'EP')
            ->with('assignee')
            ->withCount('workers')
            ->slaPriority();
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return view('ep.index', [
            'permits' => $query->paginate(15)->withQueryString(),
            'status' => $status,
            'counts' => [
                'all' => WorkPermit::where('assigned_division', 'EP')->count(),
                'ep_review' => WorkPermit::needsEpAction()->count(),
                'approved' => WorkPermit::where('assigned_division', 'EP')->where('status', 'approved')->count(),
                'rejected' => WorkPermit::where('assigned_division', 'EP')->where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function decide(Request $request, string $token, WorkPermitWorkflowNotifier $notifier, PermitAssignmentService $assignments): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'review_notes' => [
                $request->input('decision') === 'rejected' ? 'required' : 'nullable',
                'string',
                'max:1000',
            ],
        ], [
            'review_notes.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $permit = DB::transaction(function () use ($request, $token, $data, $assignments): WorkPermit {
            $permit = WorkPermit::where('public_token', $token)->lockForUpdate()->firstOrFail();
            abort_unless($permit->assigned_division === 'EP' && $permit->status === 'ep_review', 409, 'Permohonan sudah diproses atau bukan kewenangan EP.');
            $assignments->ensureWorkAssignee($permit, $request->user());

            $permit->update([
                'status' => $data['decision'],
                'review_notes' => $data['review_notes'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'deposit_required' => false,
                'security_deposit' => false,
            ]);
            $permit->statusLogs()->create([
                'actor_id' => $request->user()->id,
                'from_status' => 'ep_review',
                'to_status' => $data['decision'],
                'action' => 'ep_decision',
                'notes' => $data['review_notes'] ?? null,
            ]);

            return $permit;
        });

        $notifier->notifyApplicant(
            $permit,
            $permit->status === 'approved' ? 'Permohonan Izin Kerja Disetujui' : 'Permohonan Izin Kerja Ditolak',
            $permit->status === 'approved'
                ? 'Divisi EP telah menyetujui permohonan izin kerja Anda.'
                : ($permit->review_notes ?: 'Permohonan belum dapat disetujui.'),
        );

        return redirect()->route('ep.index')->with(
            $permit->status === 'approved' ? 'success' : 'info',
            "Permohonan {$permit->permit_number} telah {$permit->status_label}.",
        );
    }

    public function readNotification(Request $request, PermitNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        abort_unless($notification->workPermit?->assigned_division === 'EP', 404);
        $notification->markRead();

        return redirect()->route('staff.work-permits.show', $notification->workPermit->public_token);
    }
}
