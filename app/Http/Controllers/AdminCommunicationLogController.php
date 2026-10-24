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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminCommunicationLogController extends Controller
{
    private const CHANNELS = ['email', 'whatsapp', 'app'];

    private const STATUSES = ['sent', 'failed', 'queued', 'processing', 'retrying', 'skipped', 'read', 'unread'];

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'channel' => ['nullable', Rule::in(self::CHANNELS)],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'permit_type' => ['nullable', Rule::in(['loading', 'work', 'event'])],
            'q' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $items = collect();
        $channel = $validated['channel'] ?? '';

        if ($channel === '' || $channel === 'email') {
            $items->push(...$this->emailItems($validated));
        }
        if ($channel === '' || $channel === 'whatsapp') {
            $items->push(...$this->whatsappItems($validated));
        }
        if ($channel === '' || $channel === 'app') {
            $items->push(...$this->appItems($validated));
        }

        if ($query = mb_strtolower(trim((string) ($validated['q'] ?? '')))) {
            $items = $items->filter(function (array $item) use ($query): bool {
                $haystack = mb_strtolower(implode(' ', [
                    $item['recipient'],
                    $item['subject'],
                    $item['reference'],
                    $item['permit_label'],
                ]));

                return str_contains($haystack, $query);
            });
        }

        $summary = $this->summary($items);
        if ($status = $validated['status'] ?? null) {
            $items = $items->where('status', $status);
        }

        $items = $items->sortByDesc('created_at')->values();
        $page = max(1, (int) $request->query('page', 1));
        $logs = new LengthAwarePaginator($items->forPage($page, 25), $items->count(), 25, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return view('admin.communication-logs.index', [
            'logs' => $logs,
            'summary' => $summary,
            'filters' => [
                'channel' => $channel,
                'status' => $validated['status'] ?? '',
                'permit_type' => $validated['permit_type'] ?? '',
                'q' => $validated['q'] ?? '',
                'date_from' => $validated['date_from'] ?? '',
                'date_to' => $validated['date_to'] ?? '',
            ],
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

    private function emailItems(array $filters): Collection
    {
        $query = EmailMessageLog::query()->with('emailable')->latest();
        $this->applyDateFilters($query, $filters);

        return $query->limit(500)->get()->map(function (EmailMessageLog $log): array {
            return $this->item(
                channel: 'email',
                id: $log->id,
                createdAt: $log->created_at,
                recipient: $log->maskedRecipient(),
                subject: $log->subject ?: $log->event_key ?: 'Pembaruan permohonan',
                status: $log->status,
                permit: $log->emailable,
                attempts: $log->attempts,
                failure: $log->failure_reason,
                canRetry: $log->status === 'failed',
            );
        })->filter(fn (array $item): bool => $this->matchesPermitType($item, $filters));
    }

    private function whatsappItems(array $filters): Collection
    {
        $query = WhatsappMessageLog::query()->latest();
        $this->applyDateFilters($query, $filters);
        $logs = $query->limit(500)->get();
        $loading = LoadingPermit::query()->whereIn('id', $logs->where('permit_type', 'loading')->pluck('permit_id'))->get()->keyBy('id');
        $works = WorkPermit::query()->whereIn('id', $logs->where('permit_type', 'work')->pluck('permit_id'))->get()->keyBy('id');

        return $logs->map(function (WhatsappMessageLog $log) use ($loading, $works): array {
            $permit = $log->permit_type === 'loading'
                ? $loading->get($log->permit_id)
                : ($log->permit_type === 'work' ? $works->get($log->permit_id) : null);

            return $this->item(
                channel: 'whatsapp',
                id: $log->id,
                createdAt: $log->created_at,
                recipient: $log->maskedRecipient(),
                subject: $this->eventLabel($log->event),
                status: $log->status,
                permit: $permit,
                attempts: $log->attempts,
                failure: $log->error,
                canRetry: $log->status === 'failed',
                canFallback: $log->status === 'failed' && $log->recipient_kind === 'applicant',
            );
        })->filter(fn (array $item): bool => $this->matchesPermitType($item, $filters));
    }

    private function appItems(array $filters): Collection
    {
        $query = PermitNotification::query()->with(['user:id,name', 'permit', 'workPermit'])->latest();
        $this->applyDateFilters($query, $filters);

        return $query->limit(500)->get()->map(function (PermitNotification $log): array {
            return $this->item(
                channel: 'app',
                id: $log->id,
                createdAt: $log->created_at,
                recipient: $log->user?->name ?: 'Akun tidak tersedia',
                subject: $log->title,
                status: $log->read_at ? 'read' : 'unread',
                permit: $log->permit ?: $log->workPermit,
            );
        })->filter(fn (array $item): bool => $this->matchesPermitType($item, $filters));
    }

    private function item(
        string $channel,
        int $id,
        mixed $createdAt,
        string $recipient,
        string $subject,
        string $status,
        mixed $permit,
        ?int $attempts = null,
        ?string $failure = null,
        bool $canRetry = false,
        bool $canFallback = false,
    ): array {
        $permitType = $this->permitType($permit);

        return [
            'channel' => $channel,
            'channel_label' => match ($channel) {
                'whatsapp' => 'WhatsApp',
                'app' => 'Aplikasi',
                default => 'Email',
            },
            'id' => $id,
            'created_at' => $createdAt,
            'recipient' => $recipient,
            'subject' => $subject,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'reference' => $permit?->permit_number,
            'permit_type' => $permitType,
            'permit_label' => match ($permitType) {
                'loading' => 'Loading / unloading',
                'event' => 'Surat izin event',
                'work' => 'Surat izin kerja',
                default => 'Sistem',
            },
            'detail_url' => $this->permitUrl($permit),
            'attempts' => $attempts,
            'failure' => $this->safeFailure($failure),
            'can_retry' => $canRetry,
            'can_fallback' => $canFallback,
        ];
    }

    private function applyDateFilters(mixed $query, array $filters): void
    {
        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }

    private function matchesPermitType(array $item, array $filters): bool
    {
        return blank($filters['permit_type'] ?? null) || $item['permit_type'] === $filters['permit_type'];
    }

    private function permitType(mixed $permit): ?string
    {
        if ($permit instanceof LoadingPermit) {
            return 'loading';
        }
        if ($permit instanceof WorkPermit) {
            return $permit->isEventPermit() ? 'event' : 'work';
        }

        return null;
    }

    private function permitUrl(mixed $permit): ?string
    {
        return match (true) {
            $permit instanceof LoadingPermit => route('admin.loading.show', $permit->permit_number),
            $permit instanceof WorkPermit => route('staff.work-permits.show', $permit->public_token),
            default => null,
        };
    }

    private function summary(Collection $items): array
    {
        return [
            'total' => $items->count(),
            'successful' => $items->whereIn('status', ['sent', 'read'])->count(),
            'pending' => $items->whereIn('status', ['queued', 'processing', 'retrying', 'unread'])->count(),
            'failed' => $items->whereIn('status', ['failed', 'skipped'])->count(),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'sent' => 'Diterima kanal',
            'failed' => 'Gagal',
            'queued' => 'Mengantre',
            'processing' => 'Diproses',
            'retrying' => 'Dikirim ulang',
            'skipped' => 'Dilewati',
            'read' => 'Dibaca',
            'unread' => 'Belum dibaca',
            default => ucfirst($status),
        };
    }

    private function eventLabel(string $event): string
    {
        return str($event)->replace('_', ' ')->headline()->toString();
    }

    private function safeFailure(?string $failure): ?string
    {
        if (blank($failure)) {
            return null;
        }

        $safe = preg_replace([
            '/https?:\/\/\S+/i',
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
            '/(?<!\d)\+?\d[\d\s().-]{7,}\d(?!\d)/',
        ], ['[alamat disamarkan]', '[email disamarkan]', '[nomor disamarkan]'], $failure);

        return str((string) $safe)->squish()->limit(240)->toString();
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
