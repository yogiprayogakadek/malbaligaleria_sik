<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\WorkPermit;
use App\Notifications\LoadingPermitApplicantMail;
use App\Services\PermitAssignmentService;
use App\Services\PermitRevisionService;
use App\Services\WhatsappNotificationDispatcher;
use App\Services\WorkPermitWorkflowNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class PermitRevisionController extends Controller
{
    public function store(
        Request $request,
        string $type,
        int $permitId,
        PermitRevisionService $revisions,
        PermitAssignmentService $assignments,
        WorkPermitWorkflowNotifier $workNotifier,
        WhatsappNotificationDispatcher $whatsapp,
    ): RedirectResponse {
        $permit = $type === 'loading'
            ? LoadingPermit::findOrFail($permitId)
            : WorkPermit::findOrFail($permitId);
        $this->authorizeRequest($request, $permit);

        $selected = $revisions->validateSelection(
            $permit,
            (array) $request->input('fields', []),
            (array) $request->input('reasons', []),
        );

        [$permit, $items] = DB::transaction(function () use ($request, $permit, $selected, $revisions, $assignments): array {
            $locked = $permit instanceof LoadingPermit
                ? LoadingPermit::lockForUpdate()->findOrFail($permit->id)
                : WorkPermit::lockForUpdate()->findOrFail($permit->id);
            $this->authorizeRequest($request, $locked);

            if ($locked instanceof LoadingPermit) {
                $assignments->ensureLoadingAssignee($locked, $request->user());
            } else {
                $assignments->ensureWorkAssignee($locked, $request->user());
            }

            $fromStatus = $locked->status;
            $items = $revisions->create($locked, $request->user(), $selected);
            $summary = $revisions->summary($locked, $items);
            $locked->update([
                'status' => 'revision_required',
                'revision_return_status' => $fromStatus,
                'review_notes' => 'Perbaikan diminta pada: '.$summary.'.',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            if ($locked instanceof WorkPermit) {
                $locked->statusLogs()->create([
                    'actor_id' => $request->user()->id,
                    'from_status' => $fromStatus,
                    'to_status' => 'revision_required',
                    'action' => 'revision_requested',
                    'notes' => 'Perbaikan diminta pada: '.$summary.'.',
                    'metadata' => ['fields' => array_keys($selected), 'batch_id' => $items->first()->batch_id],
                ]);
            }

            return [$locked, $items];
        });

        $summary = $revisions->summary($permit, $items);
        if ($permit instanceof WorkPermit) {
            $workNotifier->notifyApplicant(
                $permit,
                'Permohonan Izin Kerja Perlu Diperbaiki',
                'Perbaiki field berikut sebelum pemeriksaan dilanjutkan: '.$summary.'.',
            );
        } else {
            if ($permit->user_id) {
                PermitNotification::create([
                    'user_id' => $permit->user_id,
                    'permit_id' => $permit->id,
                    'type' => 'revision_required',
                    'title' => 'Permohonan Perlu Diperbaiki',
                    'body' => 'Perbaiki field berikut: '.$summary.'.',
                ]);
            }
            if ($permit->applicant_email) {
                Notification::route('mail', [$permit->applicant_email => $permit->applicant_name])
                    ->notify(new LoadingPermitApplicantMail($permit, LoadingPermitApplicantMail::REVISION));
            }
            $whatsapp->loading(
                $permit,
                'revision_requested_'.$items->first()->batch_id,
                'Permohonan Loading / Unloading Perlu Diperbaiki',
                'Perbaiki field berikut sebelum pemeriksaan dilanjutkan: '.$summary.'.',
            );
        }

        return back()->with('info', 'Permintaan revisi telah dikirim kepada pemohon.');
    }

    private function authorizeRequest(Request $request, LoadingPermit|WorkPermit $permit): void
    {
        $user = $request->user();
        abort_unless($user?->is_active && $user->isValidator(), 403);

        if ($permit instanceof LoadingPermit) {
            abort_unless($user->division === 'TR', 403);
            abort_unless($permit->status === 'pending', 409, 'Permohonan tidak dapat diminta revisi pada status ini.');

            return;
        }

        $expectedStatus = match ($permit->assigned_division) {
            'TR' => 'tr_review',
            'EP' => 'ep_review',
            default => 'mep_review',
        };
        abort_unless($user->division === $permit->assigned_division, 403);
        abort_unless($permit->status === $expectedStatus, 409, 'Permohonan tidak dapat diminta revisi pada status ini.');
    }
}
