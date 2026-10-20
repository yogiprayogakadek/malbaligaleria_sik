<?php

namespace App\Services;

use App\Events\LoadingPermitSubmitted;
use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\User;
use App\Models\WorkPermit;
use App\Notifications\PermitSlaOverduePush;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueueReminderService
{
    public function __construct(private WorkPermitWorkflowNotifier $workNotifier) {}

    public function send(LoadingPermit|WorkPermit $permit, ?string $customMessage = null): bool
    {
        if (! $permit->requiresStaffSlaAction()) {
            return false;
        }

        $type = $permit instanceof LoadingPermit ? 'loading' : 'work';
        $division = $permit instanceof LoadingPermit ? 'TR' : $permit->slaDivision();
        if (! User::query()->where('is_active', true)->where('role', 'validator')->where('division', $division)->exists()) {
            return false;
        }
        if (! Cache::add("queue-reminder:{$type}:{$permit->id}", true, now()->addMinutes(5))) {
            return false;
        }

        $title = 'Pengingat antrean pemeriksaan';
        $body = $customMessage ?: "Mohon periksa permohonan {$permit->permit_number} pada antrean {$division}.";

        if ($permit instanceof WorkPermit) {
            $this->workNotifier->notifyDivision(
                $permit,
                $division,
                $title,
                $body,
                'work',
                $this->workPendingCount($division),
                false,
            );

            return true;
        }

        $recipients = User::query()
            ->where('is_active', true)
            ->where('role', 'validator')
            ->where('division', 'TR')
            ->get();
        $pendingCount = LoadingPermit::query()->where('status', 'pending')->count();

        foreach ($recipients as $recipient) {
            $notification = PermitNotification::query()->create([
                'user_id' => $recipient->id,
                'permit_id' => $permit->id,
                'type' => 'manual_queue_reminder',
                'title' => $title,
                'body' => $body,
            ]);

            try {
                LoadingPermitSubmitted::dispatch($notification, $permit, $pendingCount);
                $recipient->notify(new PermitSlaOverduePush($permit, $title, $body));
            } catch (Throwable $exception) {
                Log::warning('Pengingat antrean loading gagal dikirim.', [
                    'permit_id' => $permit->id,
                    'recipient_id' => $recipient->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return true;
    }

    private function workPendingCount(string $division): int
    {
        return match ($division) {
            'TR' => WorkPermit::needsTrAction()->count(),
            'FIN' => WorkPermit::query()->where('status', 'payment_review')->count(),
            'EP' => WorkPermit::needsEpAction()->count(),
            default => WorkPermit::needsMepAction()->count(),
        };
    }
}
