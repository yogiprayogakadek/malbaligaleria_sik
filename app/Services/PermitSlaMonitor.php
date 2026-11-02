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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PermitSlaMonitor
{
    public function __construct(private readonly WhatsappNotificationDispatcher $whatsapp) {}

    public function run(): int
    {
        return $this->scanLoading()
            + $this->scanWorkPermits('work')
            + $this->scanWorkPermits('event');
    }

    private function scanLoading(): int
    {
        $hours = SlaSetting::hoursFor('loading');
        $count = 0;

        LoadingPermit::query()
            ->where('status', 'pending')
            ->whereNotNull('sla_started_at')
            ->where('sla_started_at', '<=', now()->subHours($hours))
            ->eachById(function (LoadingPermit $permit) use (&$count, $hours): void {
                $claim = $this->claim($permit, $hours);
                if (! $claim) {
                    return;
                }

                [$claimed, $level] = $claim;
                $this->deliver($claimed, 'TR', $level, LoadingPermit::query()->where('status', 'pending')->count());
                $count++;
            });

        return $count;
    }

    private function scanWorkPermits(string $kind): int
    {
        $hours = SlaSetting::hoursFor($kind);
        $count = 0;

        WorkPermit::query()
            ->where('permit_kind', $kind)
            ->whereNotNull('sla_started_at')
            ->where('sla_started_at', '<=', now()->subHours($hours))
            ->where(function ($query): void {
                $query->whereIn('status', ['tr_review', 'mep_review', 'ep_review', 'payment_review', 'mep_final_review', 'refund_processing'])
                    ->orWhere(fn ($completed) => $completed->where('status', 'completed')->where('deposit_required', true));
            })
            ->eachById(function (WorkPermit $permit) use (&$count, $hours): void {
                $claim = $this->claim($permit, $hours);
                if (! $claim) {
                    return;
                }

                [$claimed, $level] = $claim;
                $division = $claimed->slaDivision();
                $this->deliver($claimed, $division, $level, $this->workPendingCount($division));
                $count++;
            });

        return $count;
    }

    private function claim(LoadingPermit|WorkPermit $permit, int $hours): ?array
    {
        return DB::transaction(function () use ($permit, $hours): ?array {
            /** @var LoadingPermit|WorkPermit|null $locked */
            $locked = $permit::query()->lockForUpdate()->find($permit->id);
            if (! $locked || $locked->status !== $permit->status || ! $locked->requiresStaffSlaAction() || ! $locked->sla_started_at) {
                return null;
            }

            $elapsed = $locked->sla_started_at->diffInSeconds(now());
            $level = min(3, intdiv($elapsed, max(1, $hours) * 3600));
            $currentLevel = $locked->sla_escalated_status === $locked->status
                ? (int) $locked->sla_escalation_level
                : 0;
            if ($level < 1 || $level <= $currentLevel) {
                return null;
            }

            $locked->updateQuietly([
                'sla_escalated_at' => now(),
                'sla_escalated_status' => $locked->status,
                'sla_escalation_level' => $level,
            ]);

            return [$locked->fresh(), $level];
        }, 3);
    }

    private function deliver(LoadingPermit|WorkPermit $permit, string $division, int $level, int $pendingCount): void
    {
        [$title, $body] = $this->message($permit, $level);

        if ($level === 1) {
            $this->notify($permit, $this->validators($division), 'sla_overdue', $title, $body, $pendingCount, $level);

            return;
        }

        if ($level === 2) {
            $this->whatsapp->slaStaff($permit, $this->validators($division), 'sla_level_2_'.$permit->status, $title, $body);

            return;
        }

        $admins = $this->admins();
        $this->notify($permit, $admins, 'sla_critical', $title, $body, $pendingCount, $level);
        $this->whatsapp->slaStaff($permit, $admins, 'sla_level_3_'.$permit->status, $title, $body);
    }

    private function notify(
        LoadingPermit|WorkPermit $permit,
        Collection $recipients,
        string $type,
        string $title,
        string $body,
        int $pendingCount,
        int $level,
    ): void {
        foreach ($recipients as $recipient) {
            $attributes = [
                'user_id' => $recipient->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
            ];
            $attributes[$permit instanceof LoadingPermit ? 'permit_id' : 'work_permit_id'] = $permit->id;
            $notification = PermitNotification::query()->create($attributes);

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
                    'level' => $level,
                    'exception' => $exception->getMessage(),
                ]);
            }

            try {
                $recipient->notify(new PermitSlaOverduePush($permit, $title, $body, $level));
            } catch (Throwable $exception) {
                Log::warning('Push eskalasi SLA gagal dimasukkan ke antrean.', [
                    'permit_type' => $permit instanceof LoadingPermit ? 'loading' : 'work',
                    'permit_id' => $permit->id,
                    'recipient_id' => $recipient->id,
                    'level' => $level,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function message(LoadingPermit|WorkPermit $permit, int $level): array
    {
        $type = $permit instanceof LoadingPermit ? 'loading/unloading' : strtolower($permit->permit_title);

        return match ($level) {
            1 => ["Antrean {$type} melewati SLA", "Permohonan {$permit->permit_number} belum diperiksa dalam batas waktu."],
            2 => ["Pengingat WhatsApp antrean {$type}", "Permohonan {$permit->permit_number} telah menunggu lebih dari dua kali batas SLA."],
            default => ["Eskalasi kritis antrean {$type}", "Permohonan {$permit->permit_number} telah mencapai tiga kali batas SLA dan memerlukan tindak lanjut admin."],
        };
    }

    private function validators(string $division): Collection
    {
        return User::query()->where('is_active', true)->where('role', 'validator')->where('division', $division)->get();
    }

    private function admins(): Collection
    {
        return User::query()->where('is_active', true)->where('role', 'admin')->get();
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
