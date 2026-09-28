<?php

namespace App\Services;

use App\Events\WorkPermitWorkflowUpdated;
use App\Models\PermitNotification;
use App\Models\User;
use App\Models\WorkPermit;
use App\Notifications\WorkPermitApplicantMail;
use App\Notifications\WorkPermitWorkflowPush;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class WorkPermitWorkflowNotifier
{
    public function notifyApplicant(WorkPermit $permit, string $title, string $body): void
    {
        if ($permit->user_id) {
            PermitNotification::create([
                'user_id' => $permit->user_id,
                'work_permit_id' => $permit->id,
                'type' => 'workflow_update',
                'title' => $title,
                'body' => $body,
            ]);
        }

        if ($permit->applicant_email) {
            Notification::route('mail', [$permit->applicant_email => $permit->applicant_name])
                ->notify(new WorkPermitApplicantMail($permit, $title, $body));
        }
    }

    public function notifyDivision(
        WorkPermit $permit,
        string $division,
        string $title,
        string $body,
        string $category,
        int $pendingCount,
        bool $includeAdmins = true,
    ): void {
        User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($division, $includeAdmins): void {
                $query->where(function ($validatorQuery) use ($division): void {
                    $validatorQuery->where('role', 'validator')->where('division', $division);
                });
                if ($includeAdmins) {
                    $query->orWhere('role', 'admin');
                }
            })
            ->get()
            ->each(function (User $recipient) use ($permit, $title, $body, $category, $pendingCount): void {
                $notification = PermitNotification::create([
                    'user_id' => $recipient->id,
                    'work_permit_id' => $permit->id,
                    'type' => 'workflow_update',
                    'title' => $title,
                    'body' => $body,
                ]);

                try {
                    WorkPermitWorkflowUpdated::dispatch($notification, $category, $pendingCount);
                } catch (Throwable $exception) {
                    Log::warning('Notifikasi realtime workflow izin kerja gagal dikirim.', [
                        'work_permit_id' => $permit->id,
                        'recipient_id' => $recipient->id,
                        'exception' => $exception->getMessage(),
                    ]);
                }

                try {
                    $recipient->notify(new WorkPermitWorkflowPush($permit, $title, $body));
                } catch (Throwable $exception) {
                    Log::warning('Push workflow izin kerja gagal dimasukkan ke antrean.', [
                        'work_permit_id' => $permit->id,
                        'recipient_id' => $recipient->id,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            });
    }
}
