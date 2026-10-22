<?php

namespace App\Http\Controllers;

use App\Models\PermitNotification;
use App\Models\WorkPermit;
use App\Services\PermitAssignmentService;
use App\Services\PermitPdfService;
use App\Services\WorkPermitWorkflowNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EPController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['all', 'ep_review', 'revision_required', 'approved', 'rejected'], true)
            ? $request->query('status')
            : 'ep_review';

        $query = WorkPermit::query()
            ->where('assigned_division', 'EP')
            ->withCount('workers')
            ->slaPriority();
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $perPageRaw = $request->query('per_page', 10);
        $perPage = $perPageRaw === 'all'
            ? max(1, WorkPermit::where('assigned_division', 'EP')->count())
            : (in_array((int) $perPageRaw, [5, 10, 20, 50, 100], true) ? (int) $perPageRaw : 10);

        return view('ep.index', [
            'permits' => $query->paginate($perPage)->withQueryString(),
            'perPageRaw' => $perPageRaw === 'all' ? 'all' : $perPage,
            'status' => $status,
            'counts' => [
                'all' => WorkPermit::where('assigned_division', 'EP')->count(),
                'ep_review' => WorkPermit::needsEpAction()->count(),
                'revision_required' => WorkPermit::where('assigned_division', 'EP')->where('status', 'revision_required')->count(),
                'approved' => WorkPermit::where('assigned_division', 'EP')->where('status', 'approved')->count(),
                'rejected' => WorkPermit::where('assigned_division', 'EP')->where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function decide(Request $request, string $token, WorkPermitWorkflowNotifier $notifier, PermitAssignmentService $assignments, PermitPdfService $pdf): RedirectResponse
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

        $permit = DB::transaction(function () use ($request, $token, $data, $assignments, $pdf): WorkPermit {
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

            if ($permit->status === 'approved') {
                $pdf->sealWorkPermit($permit);
            }

            return $permit;
        });

        $notifier->notifyApplicant(
            $permit,
            $permit->status === 'approved' ? "{$permit->permit_title} Disetujui" : "{$permit->permit_title} Ditolak",
            $permit->status === 'approved'
                ? "Divisi EP telah menyetujui {$permit->permit_title} Anda."
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
