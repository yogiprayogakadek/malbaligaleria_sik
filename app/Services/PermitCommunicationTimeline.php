<?php

namespace App\Services;

use App\Models\EmailMessageLog;
use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\WhatsappMessageLog;
use App\Models\WorkPermit;
use Illuminate\Support\Collection;

class PermitCommunicationTimeline
{
    public function forLoading(LoadingPermit $permit): Collection
    {
        $items = collect([$this->submission($permit->created_at)]);

        if ($permit->reviewed_at) {
            $items->push([
                'at' => $permit->reviewed_at,
                'channel' => 'status',
                'title' => $permit->status === 'approved' ? 'Permohonan disetujui' : 'Permohonan ditolak',
                'description' => $permit->review_notes,
                'status' => $permit->status,
                'actor' => $permit->reviewer?->name,
            ]);
        }

        return $this->appendCommunicationLogs($items, $permit, 'loading');
    }

    public function forWork(WorkPermit $permit): Collection
    {
        $items = collect([$this->submission($permit->created_at)]);

        $permit->statusLogs()
            ->with('actor:id,name')
            ->where('action', '!=', 'submitted')
            ->reorder('id')
            ->limit(200)
            ->get()
            ->each(function ($log) use ($items): void {
                $items->push([
                    'at' => $log->created_at,
                    'channel' => 'status',
                    'title' => $this->actionLabel($log->action),
                    'description' => $log->notes,
                    'status' => $log->to_status,
                    'actor' => $log->actor?->name,
                ]);
            });

        return $this->appendCommunicationLogs($items, $permit, 'work');
    }

    private function appendCommunicationLogs(Collection $items, LoadingPermit|WorkPermit $permit, string $permitType): Collection
    {
        EmailMessageLog::query()
            ->where('emailable_type', $permit->getMorphClass())
            ->where('emailable_id', $permit->id)
            ->latest('id')
            ->limit(100)
            ->get(['id', 'subject', 'event_key', 'status', 'attempts', 'sent_at', 'failed_at', 'created_at'])
            ->each(function (EmailMessageLog $log) use ($items): void {
                $items->push([
                    'at' => $log->sent_at ?? $log->failed_at ?? $log->created_at,
                    'channel' => 'email',
                    'title' => $log->subject ?: $this->eventLabel($log->event_key),
                    'description' => 'Email pemohon. Percobaan ke-'.$log->attempts.'.',
                    'status' => $log->status,
                    'actor' => null,
                ]);
            });

        WhatsappMessageLog::query()
            ->where('permit_type', $permitType)
            ->where('permit_id', $permit->id)
            ->latest('id')
            ->limit(200)
            ->get(['id', 'event', 'recipient_kind', 'status', 'sent_at', 'created_at'])
            ->groupBy(fn (WhatsappMessageLog $log): string => implode('|', [
                $log->event,
                $log->recipient_kind,
                $log->status,
                $log->created_at->format('Y-m-d H:i:s'),
            ]))
            ->each(function (Collection $logs) use ($items): void {
                $first = $logs->first();
                $recipient = $first->recipient_kind === 'applicant' ? 'pemohon' : 'staf';
                $sentAt = $logs->pluck('sent_at')->filter()->sortDesc()->first();
                $items->push([
                    'at' => $sentAt ?? $first->created_at,
                    'channel' => 'whatsapp',
                    'title' => $this->eventLabel($first->event),
                    'description' => $logs->count()." pesan WhatsApp untuk {$recipient}.",
                    'status' => $first->status,
                    'actor' => null,
                ]);
            });

        $notificationQuery = PermitNotification::query()
            ->where($permitType === 'loading' ? 'permit_id' : 'work_permit_id', $permit->id)
            ->latest('id')
            ->limit(200)
            ->get(['id', 'type', 'title', 'read_at', 'created_at']);

        $notificationQuery
            ->groupBy(fn (PermitNotification $log): string => implode('|', [
                $log->type,
                $log->title,
                $log->created_at->format('Y-m-d H:i:s'),
            ]))
            ->each(function (Collection $logs) use ($items): void {
                $first = $logs->first();
                $readCount = $logs->whereNotNull('read_at')->count();
                $items->push([
                    'at' => $first->created_at,
                    'channel' => 'push',
                    'title' => $first->title,
                    'description' => $logs->count().' notifikasi aplikasi dibuat'.($readCount ? "; {$readCount} sudah dibaca." : '. Push perangkat diantrekan bila aktif.'),
                    'status' => $readCount === $logs->count() ? 'read' : 'created',
                    'actor' => null,
                ]);
            });

        return $items
            ->filter(fn (array $item): bool => $item['at'] !== null)
            ->sortByDesc(fn (array $item): int => $item['at']->getTimestamp())
            ->take(250)
            ->values();
    }

    private function submission(mixed $at): array
    {
        return [
            'at' => $at,
            'channel' => 'status',
            'title' => 'Permohonan diajukan',
            'description' => null,
            'status' => 'submitted',
            'actor' => null,
        ];
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'submitted' => 'Permohonan diajukan',
            'tr_decision' => 'Keputusan TR dicatat',
            'ep_decision' => 'Keputusan EP dicatat',
            'deposit_requested' => 'Security deposit diminta',
            'approved_without_deposit' => 'Disetujui tanpa deposit',
            'payment_proof_uploaded' => 'Bukti pembayaran diunggah',
            'payment_verified' => 'Pembayaran diverifikasi',
            'payment_revision_requested' => 'Perbaikan bukti pembayaran diminta',
            'mep_final_decision' => 'Keputusan akhir MEP dicatat',
            'work_completed' => 'Pekerjaan ditandai selesai',
            'refund_started' => 'Pengembalian deposit dimulai',
            'refund_completed' => 'Pengembalian deposit selesai',
            'assignment_claimed' => 'Permohonan diambil validator',
            'assignment_taken_over' => 'Permohonan diambil alih',
            'assignment_set_by_admin' => 'Validator ditugaskan admin',
            'assignment_released_by_admin' => 'Penugasan dilepas admin',
            default => ucfirst(str_replace('_', ' ', $action)),
        };
    }

    private function eventLabel(?string $event): string
    {
        if (! $event) {
            return 'Pembaruan permohonan';
        }

        return ucfirst(str_replace('_', ' ', preg_replace('/_\d+$/', '', $event) ?: $event));
    }
}
