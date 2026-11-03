<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\WorkPermit;
use App\Notifications\LoadingPermitApplicantMail;
use App\Services\PermitPdfService;
use App\Services\WhatsappNotificationDispatcher;
use App\Services\WorkPermitWorkflowNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class AdminPermitReissueController extends Controller
{
    public function loading(
        Request $request,
        LoadingPermit $loadingPermit,
        PermitPdfService $pdf,
        WhatsappNotificationDispatcher $whatsapp,
    ): RedirectResponse {
        $reason = $this->reason($request);
        $permit = $pdf->reissueLoadingPermit($loadingPermit, $request->user(), $reason);

        try {
            if ($permit->user_id) {
                PermitNotification::query()->create([
                    'user_id' => $permit->user_id,
                    'permit_id' => $permit->id,
                    'type' => 'document_reissued',
                    'title' => 'Surat Izin Diterbitkan Ulang',
                    'body' => "Surat loading #{$permit->permit_number} telah diterbitkan ulang. Gunakan surat terbaru dari halaman status.",
                ]);
            }
            if ($permit->applicant_email) {
                Notification::route('mail', [$permit->applicant_email => $permit->applicant_name])
                    ->notify(new LoadingPermitApplicantMail($permit, LoadingPermitApplicantMail::REISSUED));
            }
            $whatsapp->loadingApplicant(
                $permit,
                'document_reissued_'.$permit->official_document_id,
                'Surat Izin Loading Diterbitkan Ulang',
                'Surat sebelumnya tidak lagi berlaku. Unduh surat terbaru melalui tautan status.',
            );
        } catch (Throwable $exception) {
            $this->logNotificationFailure($permit, $exception);
        }

        return back()->with('success', "Surat {$permit->permit_number} diterbitkan ulang. QR dan hash lama sudah dinonaktifkan.");
    }

    public function work(
        Request $request,
        WorkPermit $workPermit,
        PermitPdfService $pdf,
        WorkPermitWorkflowNotifier $notifier,
    ): RedirectResponse {
        $reason = $this->reason($request);
        $permit = $pdf->reissueWorkPermit($workPermit, $request->user(), $reason);

        try {
            $notifier->notifyApplicantOnly(
                $permit,
                $permit->isEventPermit() ? 'Surat Izin Event Diterbitkan Ulang' : 'Surat Izin Kerja Diterbitkan Ulang',
                'Surat sebelumnya tidak lagi berlaku. Unduh surat terbaru melalui tautan status.',
            );
        } catch (Throwable $exception) {
            $this->logNotificationFailure($permit, $exception);
        }

        return back()->with('success', "Surat {$permit->permit_number} diterbitkan ulang. QR dan hash lama sudah dinonaktifkan.");
    }

    private function reason(Request $request): string
    {
        return $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'current_password' => ['required', 'current_password'],
        ], [
            'reason.required' => 'Alasan penerbitan ulang wajib diisi.',
            'reason.min' => 'Alasan penerbitan ulang minimal 10 karakter.',
        ])['reason'];
    }

    private function logNotificationFailure(LoadingPermit|WorkPermit $permit, Throwable $exception): void
    {
        Log::warning('Surat berhasil diterbitkan ulang, tetapi notifikasi pemohon gagal dijadwalkan.', [
            'permit_type' => $permit instanceof LoadingPermit ? 'loading' : ($permit->isEventPermit() ? 'event' : 'work'),
            'permit_id' => $permit->id,
            'document_id' => $permit->official_document_id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
