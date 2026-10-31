<?php

namespace App\Services;

use App\Events\FormPresenceUpdated;
use App\Models\FormPresence;
use Illuminate\Support\Facades\Log;

class FormPresenceService
{
    public const TYPES = [
        'loading' => 'Loading / Unloading',
        'work' => 'Surat Izin Kerja',
        'event' => 'Surat Izin Event',
    ];

    private const EXPIRES_AFTER_SECONDS = 75;

    public function heartbeat(string $type, string $visitorId, string $tabId): array
    {
        $identity = $this->identity($visitorId, $tabId);
        $now = now();
        $wasActive = FormPresence::query()
            ->where('form_type', $type)
            ->where('tab_hash', $identity['tab_hash'])
            ->where('expires_at', '>', $now)
            ->exists();

        FormPresence::query()->upsert([[
            'form_type' => $type,
            'tab_hash' => $identity['tab_hash'],
            'visitor_hash' => $identity['visitor_hash'],
            'last_seen_at' => $now,
            'expires_at' => $now->copy()->addSeconds(self::EXPIRES_AFTER_SECONDS),
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['form_type', 'tab_hash'], ['visitor_hash', 'last_seen_at', 'expires_at', 'updated_at']);

        if (! $wasActive) {
            $this->broadcastSnapshot();
        }

        return $this->snapshot();
    }

    public function leave(string $type, string $visitorId, string $tabId): array
    {
        $identity = $this->identity($visitorId, $tabId);
        $deleted = FormPresence::query()
            ->where('form_type', $type)
            ->where('tab_hash', $identity['tab_hash'])
            ->delete();

        if ($deleted > 0) {
            $this->broadcastSnapshot();
        }

        return $this->snapshot();
    }

    public function snapshot(): array
    {
        $active = FormPresence::query()
            ->where('expires_at', '>', now())
            ->selectRaw('form_type, COUNT(DISTINCT visitor_hash) as aggregate')
            ->groupBy('form_type')
            ->pluck('aggregate', 'form_type');

        $counts = collect(self::TYPES)
            ->mapWithKeys(fn (string $label, string $type): array => [$type => (int) ($active[$type] ?? 0)])
            ->all();

        return [
            'counts' => $counts,
            'total' => array_sum($counts),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    public function prune(): int
    {
        $deleted = FormPresence::query()->where('expires_at', '<=', now())->delete();

        if ($deleted > 0) {
            $this->broadcastSnapshot();
        }

        return $deleted;
    }

    private function identity(string $visitorId, string $tabId): array
    {
        $key = (string) config('app.key');

        return [
            'visitor_hash' => hash_hmac('sha256', $visitorId, $key),
            'tab_hash' => hash_hmac('sha256', $visitorId.'|'.$tabId, $key),
        ];
    }

    private function broadcastSnapshot(): void
    {
        try {
            FormPresenceUpdated::dispatch($this->snapshot());
        } catch (\Throwable $exception) {
            Log::warning('Pembaruan realtime aktivitas form belum dapat dikirim.', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
