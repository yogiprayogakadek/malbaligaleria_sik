<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\WorkPermit;
use App\Notifications\LoadingPermitApplicantMail;
use App\Services\WhatsappNotificationDispatcher;
use App\Services\WorkPermitWorkflowNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class PermitRevocationController extends Controller
{
    private const WORK_LETTER_STATUSES = ['approved', 'completed', 'refund_processing', 'refunded'];

    public function loading(
        Request $request,
        string $permitNumber,
        WhatsappNotificationDispatcher $whatsapp,
    ): RedirectResponse {
        $data = $this->validateReason($request);

        $permit = DB::transaction(function () use ($request, $permitNumber, $data): LoadingPermit {
            $permit = LoadingPermit::where('permit_number', $permitNumber)->lockForUpdate()->firstOrFail();
            $this->authorizeLoading($request, $permit);
            abort_if($permit->revoked_at, 409, 'Surat izin ini sudah dicabut.');
            abort_unless($permit->status === 'approved', 409, 'Hanya surat yang telah disetujui yang dapat dicabut.');

            $permit->update([
                'revoked_at' => now(),
                'revoked_by' => $request->user()->id,
                'revocation_reason' => $data['revocation_reason'],
            ]);

            if ($permit->user_id) {
                PermitNotification::create([
                    'user_id' => $permit->user_id,
                    'permit_id' => $permit->id,
                    'type' => 'revoked',
                    'title' => 'Surat Izin Dicabut',
                    'body' => "Surat loading #{$permit->permit_number} dicabut. Alasan: {$data['revocation_reason']}",
                ]);
            }

            return $permit;
        });

        if ($permit->applicant_email) {
            Notification::route('mail', [$permit->applicant_email => $permit->applicant_name])
                ->notify(new LoadingPermitApplicantMail($permit, LoadingPermitApplicantMail::REVOKED));
        }

        $whatsapp->loading(
            $permit,
            'revoked_'.$permit->revoked_at->timestamp,
            'Surat Izin Loading Dicabut',
            $permit->revocation_reason,
        );

        return back()->with('info', "Surat {$permit->permit_number} telah dicabut dan QR tidak lagi berlaku.");
    }

    public function work(
        Request $request,
        string $token,
        WorkPermitWorkflowNotifier $notifier,
    ): RedirectResponse {
        $data = $this->validateReason($request);

        $permit = DB::transaction(function () use ($request, $token, $data): WorkPermit {
            $permit = WorkPermit::where('public_token', $token)->lockForUpdate()->firstOrFail();
            $this->authorizeWork($request, $permit);
            abort_if($permit->revoked_at, 409, 'Surat izin ini sudah dicabut.');
            abort_unless(in_array($permit->status, self::WORK_LETTER_STATUSES, true), 409, 'Surat resmi belum tersedia untuk dicabut.');

            $permit->update([
                'revoked_at' => now(),
                'revoked_by' => $request->user()->id,
                'revocation_reason' => $data['revocation_reason'],
            ]);
            $permit->statusLogs()->create([
                'actor_id' => $request->user()->id,
                'from_status' => $permit->status,
                'to_status' => $permit->status,
                'action' => 'permit_revoked',
                'notes' => $data['revocation_reason'],
            ]);

            return $permit;
        });

        $notifier->notifyApplicant(
            $permit,
            'Surat Izin Kerja Dicabut',
            'Surat izin tidak lagi berlaku. Alasan: '.$permit->revocation_reason,
        );

        return back()->with('info', "Surat {$permit->permit_number} telah dicabut dan QR tidak lagi berlaku.");
    }

    private function validateReason(Request $request): array
    {
        return $request->validate([
            'revocation_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'revocation_reason.required' => 'Alasan pencabutan wajib diisi.',
            'revocation_reason.min' => 'Alasan pencabutan minimal 10 karakter.',
        ]);
    }

    private function authorizeLoading(Request $request, LoadingPermit $permit): void
    {
        $user = $request->user();
        abort_unless($user?->is_active, 403);
        abort_unless($user->isAdmin() || ($user->isValidator() && $user->division === 'TR'), 403);
    }

    private function authorizeWork(Request $request, WorkPermit $permit): void
    {
        $user = $request->user();
        abort_unless($user?->is_active, 403);
        abort_unless(
            $user->isAdmin()
            || ($user->isValidator() && $user->division === $permit->assigned_division && $user->division !== 'FIN'),
            403,
        );
    }
}
