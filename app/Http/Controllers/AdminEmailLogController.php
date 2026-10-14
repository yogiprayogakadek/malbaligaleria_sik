<?php

namespace App\Http\Controllers;

use App\Models\EmailMessageLog;
use App\Models\LoadingPermit;
use App\Models\WorkPermit;
use App\Notifications\LoadingPermitApplicantMail;
use App\Notifications\WorkPermitApplicantMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class AdminEmailLogController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $logs = EmailMessageLog::query()
            ->when(in_array($status, ['sent', 'failed', 'retrying'], true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.email-logs.index', compact('logs', 'status'));
    }

    public function retry(EmailMessageLog $emailLog)
    {
        abort_unless($emailLog->status === 'failed', 409, 'Hanya email gagal yang dapat dikirim ulang.');

        $permit = $emailLog->emailable;
        abort_unless($permit instanceof LoadingPermit || $permit instanceof WorkPermit, 404);

        if ($permit instanceof LoadingPermit) {
            $notification = new LoadingPermitApplicantMail($permit, $emailLog->event_key ?: LoadingPermitApplicantMail::SUBMITTED);
        } else {
            $notification = new WorkPermitApplicantMail(
                $permit,
                $emailLog->subject ?: 'Pembaruan Permohonan',
                'Terdapat pembaruan pada permohonan Anda. Silakan buka tautan status untuk melihat informasi terbaru.'
            );
        }

        $notification->id = $emailLog->notification_id;

        $emailLog->update([
            'status' => 'retrying',
            'attempts' => $emailLog->attempts + 1,
            'failure_reason' => null,
        ]);

        Notification::route('mail', $emailLog->recipient)->notify($notification);

        return back()->with('success', 'Email dimasukkan kembali ke antrean pengiriman.');
    }
}
