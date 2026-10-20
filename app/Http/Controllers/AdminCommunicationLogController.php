<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsappMessage;
use App\Models\EmailMessageLog;
use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\WhatsappMessageLog;
use App\Models\WorkPermit;
use App\Notifications\LoadingPermitApplicantMail;
use App\Notifications\WorkPermitApplicantMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminCommunicationLogController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'channel' => ['nullable', Rule::in(['email', 'whatsapp', 'push'])],
            'status' => ['nullable', Rule::in(['sent', 'failed', 'queued', 'retrying', 'read', 'unread'])],
        ]);
        $items = collect();
        if (! isset($validated['channel']) || $validated['channel'] === 'email') {
            $items->push(...EmailMessageLog::query()->latest()->limit(300)->get()->map(fn ($log) => [
                'channel' => 'email', 'id' => $log->id, 'created_at' => $log->created_at,
                'recipient' => $log->maskedRecipient(), 'subject' => $log->subject ?: $log->event_key,
                'status' => $log->status, 'reference' => $log->emailable?->permit_number,
                'can_retry' => $log->status === 'failed', 'can_fallback' => false,
            ]));
        }
        if (! isset($validated['channel']) || $validated['channel'] === 'whatsapp') {
            $items->push(...WhatsappMessageLog::query()->latest()->limit(300)->get()->map(fn ($log) => [
                'channel' => 'whatsapp', 'id' => $log->id, 'created_at' => $log->created_at,
                'recipient' => $log->maskedRecipient(), 'subject' => $log->event,
                'status' => $log->status, 'reference' => strtoupper($log->permit_type).' #'.$log->permit_id,
                'can_retry' => $log->status === 'failed',
                'can_fallback' => $log->status === 'failed' && $log->recipient_kind === 'applicant',
            ]));
        }
        if (! isset($validated['channel']) || $validated['channel'] === 'push') {
            $items->push(...PermitNotification::query()->with('user:id,name')->latest()->limit(300)->get()->map(fn ($log) => [
                'channel' => 'push', 'id' => $log->id, 'created_at' => $log->created_at,
                'recipient' => $log->user?->name ?: 'Akun tidak tersedia', 'subject' => $log->title,
                'status' => $log->read_at ? 'read' : 'unread',
                'reference' => $log->permit_id ? 'LOADING #'.$log->permit_id : 'WORK #'.$log->work_permit_id,
                'can_retry' => false, 'can_fallback' => false,
            ]));
        }

        $items = $items->when($validated['status'] ?? null, fn ($collection, $status) => $collection->where('status', $status))->sortByDesc('created_at')->values();
        $page = max(1, (int) $request->query('page', 1));
        $logs = new LengthAwarePaginator($items->forPage($page, 25), $items->count(), 25, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);

        return view('admin.communication-logs.index', [
            'logs' => $logs,
            'channel' => $validated['channel'] ?? '',
            'status' => $validated['status'] ?? '',
        ]);
    }

    public function retryWhatsapp(WhatsappMessageLog $whatsappLog): RedirectResponse
    {
        abort_unless($whatsappLog->status === 'failed', 409, 'Hanya pesan gagal yang dapat dikirim ulang.');
        $whatsappLog->update(['status' => 'queued', 'error' => null]);
        SendWhatsappMessage::dispatch($whatsappLog->id);

        return back()->with('success', 'Pesan WhatsApp dimasukkan kembali ke antrean.');
    }

    public function fallbackToEmail(WhatsappMessageLog $whatsappLog): RedirectResponse
    {
        abort_unless($whatsappLog->status === 'failed', 409, 'Fallback hanya tersedia untuk pesan WhatsApp gagal.');
        abort_unless($whatsappLog->recipient_kind === 'applicant', 403, 'Fallback email hanya tersedia untuk pesan pemohon.');
        $permit = $whatsappLog->permit_type === 'loading'
            ? LoadingPermit::query()->find($whatsappLog->permit_id)
            : WorkPermit::query()->find($whatsappLog->permit_id);
        abort_unless($permit && filled($permit->applicant_email), 422, 'Pemohon tidak memiliki alamat email yang dapat digunakan.');

        $notification = $permit instanceof LoadingPermit
            ? new LoadingPermitApplicantMail($permit, $this->loadingMailType($whatsappLog->event))
            : new WorkPermitApplicantMail($permit, 'Pembaruan Permohonan', 'Pesan WhatsApp tidak dapat diterima. Silakan buka tautan status untuk melihat informasi terbaru.');
        Notification::route('mail', $permit->applicant_email)->notify($notification);

        return back()->with('success', 'Fallback email telah dimasukkan ke antrean.');
    }

    private function loadingMailType(string $event): string
    {
        return match (true) {
            $event === 'approved' => LoadingPermitApplicantMail::APPROVED,
            $event === 'rejected' => LoadingPermitApplicantMail::REJECTED,
            str_starts_with($event, 'revision_requested_') => LoadingPermitApplicantMail::REVISION,
            str_starts_with($event, 'revoked_') => LoadingPermitApplicantMail::REVOKED,
            default => LoadingPermitApplicantMail::SUBMITTED,
        };
    }
}
