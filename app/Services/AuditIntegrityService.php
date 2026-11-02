<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class AuditIntegrityService
{
    private const STATE_ID = 1;

    private const HASH_FIELDS = [
        'chain_uuid',
        'previous_hash',
        'actor_id',
        'event',
        'auditable_type',
        'auditable_id',
        'route_name',
        'request_method',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
        'created_at',
    ];

    public function keyIsValid(): bool
    {
        $decoded = base64_decode((string) config('audit.chain_key'), true);

        return is_string($decoded) && strlen($decoded) >= 32;
    }

    public function prepare(AuditLog $log): void
    {
        if (! Schema::hasTable('audit_chain_state')) {
            return;
        }

        $state = DB::table('audit_chain_state')->where('id', self::STATE_ID)->lockForUpdate()->first();
        if (! $state) {
            throw new RuntimeException('State rantai audit tidak tersedia.');
        }

        if ($log->getCreatedAtColumn() && ! $log->getAttribute($log->getCreatedAtColumn())) {
            $log->setCreatedAt($log->freshTimestamp());
        }

        $log->chain_uuid = (string) Str::uuid();
        $log->previous_hash = $state->last_hash;
        $log->entry_hash = $this->hash($log->getAttributes());
    }

    public function advance(AuditLog $log): void
    {
        if (blank($log->chain_uuid)) {
            return;
        }

        DB::table('audit_chain_state')->where('id', self::STATE_ID)->update([
            'last_log_id' => $log->getKey(),
            'last_hash' => $log->entry_hash,
            'updated_at' => now(),
        ]);
        Cache::forget('audit-integrity:status');
    }

    public function backfill(): void
    {
        DB::transaction(function (): void {
            DB::table('audit_chain_state')->where('id', self::STATE_ID)->lockForUpdate()->firstOrFail();
            $previousHash = null;
            $lastId = null;

            DB::table('audit_logs')->orderBy('id')->chunkById(500, function ($rows) use (&$previousHash, &$lastId): void {
                foreach ($rows as $row) {
                    $attributes = (array) $row;
                    $attributes['chain_uuid'] = (string) Str::uuid();
                    $attributes['previous_hash'] = $previousHash;
                    $attributes['entry_hash'] = $this->hash($attributes);

                    DB::table('audit_logs')->where('id', $row->id)->update([
                        'chain_uuid' => $attributes['chain_uuid'],
                        'previous_hash' => $previousHash,
                        'entry_hash' => $attributes['entry_hash'],
                    ]);

                    $previousHash = $attributes['entry_hash'];
                    $lastId = $row->id;
                }
            });

            DB::table('audit_chain_state')->where('id', self::STATE_ID)->update([
                'last_log_id' => $lastId,
                'last_hash' => $previousHash,
                'updated_at' => now(),
            ]);
        }, 3);

        Cache::forget('audit-integrity:status');
    }

    /** @return array{valid: bool, count: int, first_invalid_id: int|null, reason: string|null} */
    public function verify(): array
    {
        $previousHash = null;
        $count = 0;
        $lastId = null;

        foreach (DB::table('audit_logs')->orderBy('id')->cursor() as $row) {
            $count++;
            $attributes = (array) $row;
            $valid = filled($row->chain_uuid)
                && filled($row->entry_hash)
                && hash_equals((string) $previousHash, (string) $row->previous_hash)
                && hash_equals((string) $row->entry_hash, $this->hash($attributes));

            if (! $valid) {
                return $this->invalid($count, (int) $row->id, 'Rantai atau isi catatan tidak cocok.');
            }

            $previousHash = $row->entry_hash;
            $lastId = (int) $row->id;
        }

        $state = DB::table('audit_chain_state')->where('id', self::STATE_ID)->first();
        if (! $state
            || (int) ($state->last_log_id ?? 0) !== (int) ($lastId ?? 0)
            || ! hash_equals((string) ($state->last_hash ?? ''), (string) ($previousHash ?? ''))) {
            return $this->invalid($count, $lastId, 'Ujung rantai tidak cocok dengan state audit.');
        }

        return ['valid' => true, 'count' => $count, 'first_invalid_id' => null, 'reason' => null];
    }

    /** @return array{valid: bool, count: int, first_invalid_id: int|null, reason: string|null} */
    public function verifyCached(): array
    {
        return Cache::remember('audit-integrity:status', now()->addMinutes(5), fn (): array => $this->verify());
    }

    private function hash(array $attributes): string
    {
        $key = base64_decode((string) config('audit.chain_key'), true);
        if (! is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('AUDIT_CHAIN_KEY tidak valid.');
        }

        $payload = [];
        foreach (self::HASH_FIELDS as $field) {
            $payload[$field] = $attributes[$field] ?? null;
        }

        return hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $key);
    }

    /** @return array{valid: false, count: int, first_invalid_id: int|null, reason: string} */
    private function invalid(int $count, ?int $id, string $reason): array
    {
        return ['valid' => false, 'count' => $count, 'first_invalid_id' => $id, 'reason' => $reason];
    }
}
