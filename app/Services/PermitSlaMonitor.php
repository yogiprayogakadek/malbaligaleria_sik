<?php

namespace App\Services;

use App\Events\LoadingPermitSubmitted;
use App\Events\WorkPermitWorkflowUpdated;
use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\SlaSetting;
use App\Models\User;
use App\Models\WorkPermit;
use App\Notifications\PermitSlaOverduePush;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PermitSlaMonitor
{
    public function run(): int
    {
        $escalated = 0;
        $escalated += $this->scanLoading();
        $escalated += $this->scanWorkPermits();

        return $escalated;
    }

    private function scanLoading(): int
    {
        $cutoff = now()->subHours(SlaSetting::hoursFor('loading'));
        $count = 0;

        LoadingPermit::query()
            ->where('status', 'pending')
            ->whereNotNull('sla_started_at')
            ->where('sla_started_at', '<=', $cutoff)
            ->where(function ($query): void {
                $query->whereNull('sla_escalated_at')->orWhereColumn('sla_escalated_status', '!=', 'status');
            })
            ->eachById(function (LoadingPermit $permit) use (&$count, $cutoff): void {
                $title = 'Antrean loading melewati SLA';
                $body = "Permohonan {$permit->permit_number} belum diperiksa dalam batas waktu.";
                $recipients = $this->recipients('TR');
                $claim = $this->claimAndStore($permit, $cutoff, $recipients, $title, $body);
                if (! $claim) {
                    return;
                }

                [$claimed, $notifications] = $claim;
                $pendingCount = LoadingPermit::query()->where('status', 'pending')->count();

                $this->deliver($claimed, $recipients, $notifications, $title, $body, $pendingCount);
                $count++;
            });

        return $count;
    }

    private function scanWorkPermits(): int
    {
        $cutoff = now()->subHours(SlaSetting::hoursFor('work'));
        $count = 0;

        WorkPermit::query()
            ->whereNotNull('sla_started_at')
            ->where('sla_started_at', '<=', $cutoff)
            ->where(function ($query): void {
                $query->whereIn('status', ['tr_review', 'mep_review', 'ep_review', 'payment_review', 'mep_final_review', 'refund_processing'])
                    ->orWhere(function ($completed): void {
                        $completed->where('status', 'completed')->where('deposit_required', true);
                    });
            })
            ->where(function ($query): void {
                $query->whereNull('sla_escalated_at')->orWhereColumn('sla_escalated_status', '!=', 'status');
            })
            ->eachById(function (WorkPermit $permit) use (&$count, $cutoff): void {
                $division = $permit->slaDivision();
                $title = 'Antrean izin kerja melewati SLA';
                $body = "Permohonan {$permit->permit_number} pada tahap {$permit->status_label} belum diproses dalam batas waktu.";
                $recipients = $this->recipients($division);
                $claim = $this->claimAndStore($permit, $cutoff, $recipients, $title, $body);
                if (! $claim) {
                    return;
                }

                [$claimed, $notifications] = $claim;
                $pendingCount = $this->workPendingCount($division);

                $this->deliver($claimed, $recipients, $notifications, $title, $body, $pendingCount);
                $count++;
            });

        return $count;
    }

    private function claimAndStore(
        LoadingPermit|WorkPermit $permit,
        mixed $cutoff,
        Collection $recipients,
        string $title,
        string $body,
    ): ?array {
        return DB::transaction(function () use ($permit, $cutoff, $recipients, $title, $body): ?array {
            /** @var LoadingPermit|WorkPermit|null $locked */
            $locked = $permit::query()->lockForUpdate()->find($permit->id);
            if (! $locked || $locked->status !== $permit->status || ! $locked->requiresStaffSlaAction() || ! $locked->sla_started_at?->lte($cutoff)) {
                return null;
            }
            if ($locked->sla_escalated_at && $locked->sla_escalated_status === $locked->status) {
                return null;
            }

            $locked->updateQuietly([
                'sla_escalated_at' => now(),
                'sla_escalated_status' => $locked->status,
            ]);

            $notifications = $recipients->mapWithKeys(function (User $recipient) use ($locked, $title, $body): array {
                $attributes = [
                    'user_id' => $recipient->id,
                    'type' => 'sla_overdue',
                    'title' => $title,
                    'body' => $body,
                ];
                $attributes[$locked instanceof LoadingPermit ? 'permit_id' : 'work_permit_id'] = $locked->id;

                return [$recipient->id => PermitNotification::query()->create($attributes)];
            });

            return [$locked->fresh(), $notifications];
        }, 3);
    }

    private function recipients(string $division): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($division): void {
                $query->where('role', 'admin')
                    ->orWhere(function ($validators) use ($division): void {
                        $validators->where('role', 'validator')->where('division', $division);
                    });
            })
            ->get();
    }

    private function deliver(Model $permit, Collection $recipients, Collection $notifications, string $title, string $body, int $pendingCount): void
    {
        foreach ($recipients as $recipient) {
            $notification = $notifications->get($recipient->id);
            try {
                if ($permit instanceof LoadingPermit) {
                    LoadingPermitSubmitted::dispatch($notification, $permit, $pendingCount);
                } else {
                    WorkPermitWorkflowUpdated::dispatch($notification, 'work', $pendingCount);
                }
            } catch (Throwable $exception) {
                Log::warning('Eskalasi SLA realtime gagal dikirim.', [
                    'permit_type' => $permit instanceof LoadingPermit ? 'loading' : 'work',
                    'permit_id' => $permit->id,
                    'recipient_id' => $recipient->id,
                    'exception' => $exception->getMessage(),
                ]);
            }

            try {
                $recipient->notify(new PermitSlaOverduePush($permit, $title, $body));
            } catch (Throwable $exception) {
                Log::warning('Push eskalasi SLA gagal dimasukkan ke antrean.', [
                    'permit_type' => $permit instanceof LoadingPermit ? 'loading' : 'work',
                    'permit_id' => $permit->id,
                    'recipient_id' => $recipient->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
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
