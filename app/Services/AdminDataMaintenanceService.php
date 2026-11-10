<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EmailMessageLog;
use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\User;
use App\Models\WhatsappMessageLog;
use App\Models\WorkPermit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AdminDataMaintenanceService
{
    private const PRODUCTION_RESET_EVENT = 'production_data_reset';

    public function __construct(
        private readonly EncryptedBackupManager $backups,
        private readonly BackupCipher $backupCipher,
        private readonly AuditIntegrityService $auditIntegrity,
    ) {}

    /** @return array<string, array<string, string>> */
    public function datasets(): array
    {
        return [
            'loading' => ['label' => 'Permohonan loading / unloading', 'model' => LoadingPermit::class, 'table' => 'loading_permits'],
            'work' => ['label' => 'Permohonan surat izin kerja', 'model' => WorkPermit::class, 'table' => 'work_permits'],
            'notifications' => ['label' => 'Notifikasi dalam aplikasi', 'model' => PermitNotification::class, 'table' => 'permit_notifications'],
            'email_logs' => ['label' => 'Log pengiriman email', 'model' => EmailMessageLog::class, 'table' => 'email_message_logs'],
            'whatsapp_logs' => ['label' => 'Log pengiriman WhatsApp', 'model' => WhatsappMessageLog::class, 'table' => 'whatsapp_message_logs'],
        ];
    }

    public function query(string $dataset): Builder
    {
        $definition = $this->definition($dataset);

        return $definition['model']::query()
            ->when($dataset === 'email_logs', fn (Builder $query): Builder => $query->whereIn('status', ['sent', 'failed']))
            ->when($dataset === 'whatsapp_logs', fn (Builder $query): Builder => $query->whereIn('status', ['sent', 'failed']))
            ->latest('id');
    }

    /** @param array<int, int> $ids */
    public function deleteSelected(string $dataset, array $ids, User $admin, string $reason): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $count = $this->deleteBatch($dataset, $ids);
        $this->audit($dataset, $admin, $reason, 'selected', $count, $ids);

        return $count;
    }

    public function deleteAll(string $dataset, User $admin, string $reason): int
    {
        $count = 0;
        $this->query($dataset)->reorder()->select('id')->chunkById(200, function ($rows) use ($dataset, &$count): void {
            $count += $this->deleteBatch($dataset, $rows->pluck('id')->all());
        });
        $this->audit($dataset, $admin, $reason, 'all', $count, []);

        return $count;
    }

    public function productionResetCompleted(): ?AuditLog
    {
        return AuditLog::query()
            ->where('event', self::PRODUCTION_RESET_EVENT)
            ->latest('id')
            ->first();
    }

    public function backupConfigured(): bool
    {
        return (bool) config('backup.enabled') && $this->backupCipher->hasValidConfiguredKey();
    }

    /** @return array{deleted: int, counts: array<string, int>, backup: string, storage_cleanup_failed: array<int, string>} */
    public function resetForProduction(User $admin, string $reason, string $currentSessionId): array
    {
        if ($this->productionResetCompleted()) {
            throw ValidationException::withMessages([
                'production_reset' => 'Reset data untuk go-live sudah pernah dijalankan.',
            ]);
        }

        $lock = Cache::lock('production-data-reset', 900);
        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'production_reset' => 'Reset lain sedang berjalan. Tunggu proses tersebut selesai.',
            ]);
        }

        try {
            $backupPath = $this->backups->create();
            $backupName = basename($backupPath);

            $counts = DB::transaction(function () use ($admin, $reason, $currentSessionId, $backupName): array {
                if (AuditLog::query()->where('event', self::PRODUCTION_RESET_EVENT)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages([
                        'production_reset' => 'Reset data untuk go-live sudah pernah dijalankan.',
                    ]);
                }

                $users = User::query()
                    ->where('role', '!=', 'admin')
                    ->lockForUpdate()
                    ->get(['id', 'email']);
                $userIds = $users->pluck('id')->all();
                $emails = $users->pluck('email')->filter()->all();
                $counts = [];

                foreach ([
                    'announcement_recipients',
                    'announcement_campaigns',
                    'event_whatsapp_group_deliveries',
                    'permit_notifications',
                    'permit_revisions',
                    'official_document_versions',
                    'work_permit_status_logs',
                    'work_permit_workers',
                    'scanner_verification_logs',
                    'security_gate_events',
                    'email_message_logs',
                    'whatsapp_message_logs',
                    'web_push_message_logs',
                    'operation_traces',
                    'form_presences',
                    'system_alerts',
                    'scanner_ip_blocks',
                    'validator_delegations',
                    'jobs',
                    'job_batches',
                    'failed_jobs',
                    'permit_number_sequences',
                    'loading_permits',
                    'work_permits',
                ] as $table) {
                    $counts[$table] = DB::table($table)->delete();
                }

                $pushTable = (string) config('webpush.table_name', 'push_subscriptions');
                $counts[$pushTable] = $userIds === []
                    ? 0
                    : DB::connection(config('webpush.database_connection'))->table($pushTable)
                        ->where('subscribable_type', User::class)
                        ->whereIn('subscribable_id', $userIds)
                        ->delete();

                foreach (['staff_login_events', 'trusted_login_devices'] as $table) {
                    $counts[$table] = $userIds === [] ? 0 : DB::table($table)->whereIn('user_id', $userIds)->delete();
                }

                $sessions = DB::table(config('session.table', 'sessions'))
                    ->where(function ($query) use ($userIds): void {
                        $query->whereNull('user_id');
                        if ($userIds !== []) {
                            $query->orWhereIn('user_id', $userIds);
                        }
                    });
                if ($currentSessionId !== '') {
                    $sessions->where('id', '!=', $currentSessionId);
                }
                $counts['sessions'] = $sessions->delete();
                $counts['password_reset_tokens'] = $emails === []
                    ? 0
                    : DB::table('password_reset_tokens')->whereIn('email', $emails)->delete();
                $counts['users'] = User::query()->where('role', '!=', 'admin')->delete();
                $counts['tenants'] = DB::table('tenants')->delete();

                if ($counts['users'] > 0) {
                    $this->auditIntegrity->backfill();
                }

                AuditLog::query()->create([
                    'actor_id' => $admin->id,
                    'event' => self::PRODUCTION_RESET_EVENT,
                    'auditable_type' => self::class,
                    'auditable_id' => null,
                    'route_name' => request()->route()?->getName(),
                    'request_method' => request()->method(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'old_values' => [],
                    'new_values' => [
                        'reason' => $reason,
                        'backup' => $backupName,
                        'deleted' => array_sum($counts),
                        'counts' => $counts,
                    ],
                ]);

                return $counts;
            }, 3);

            $storageCleanupFailed = collect(['permits', 'scanner/evidence'])
                ->reject(fn (string $directory): bool => Storage::disk('local')->deleteDirectory($directory))
                ->values()
                ->all();

            if ($storageCleanupFailed !== []) {
                Log::error('Reset produksi selesai, tetapi direktori dokumen gagal dibersihkan.', [
                    'directories' => $storageCleanupFailed,
                    'admin_id' => $admin->id,
                ]);
            }

            return [
                'deleted' => array_sum($counts),
                'counts' => $counts,
                'backup' => $backupName,
                'storage_cleanup_failed' => $storageCleanupFailed,
            ];
        } finally {
            $lock->release();
        }
    }

    /** @param array<int, int> $ids */
    private function deleteBatch(string $dataset, array $ids): int
    {
        $ids = $this->query($dataset)->whereIn('id', $ids)->pluck('id')->all();
        if ($ids === []) {
            return 0;
        }

        $definition = $this->definition($dataset);
        $files = in_array($dataset, ['loading', 'work'], true) ? $this->documentPaths($dataset, $ids) : [];

        $deleted = DB::transaction(function () use ($dataset, $definition, $ids): int {
            if (in_array($dataset, ['loading', 'work'], true)) {
                $model = $dataset === 'loading' ? LoadingPermit::class : WorkPermit::class;
                DB::table('permit_revisions')->where('revisable_type', $model)->whereIn('revisable_id', $ids)->delete();
                DB::table('email_message_logs')->where('emailable_type', $model)->whereIn('emailable_id', $ids)->delete();
                DB::table('whatsapp_message_logs')->where('permit_type', $dataset)->whereIn('permit_id', $ids)->delete();
                DB::table('scanner_verification_logs')->where('permit_type', $dataset)->whereIn('permit_id', $ids)->delete();
                DB::table('security_gate_events')->where('permit_type', $dataset)->whereIn('permit_id', $ids)->delete();
            }

            return DB::table($definition['table'])->whereIn('id', $ids)->delete();
        }, 3);

        if ($files !== []) {
            Storage::disk('local')->delete($files);
        }

        return $deleted;
    }

    /** @param array<int, int> $ids
     * @return array<int, string>
     */
    private function documentPaths(string $dataset, array $ids): array
    {
        $fields = $dataset === 'loading'
            ? ['id_doc_path', 'instruction_letter_path', 'official_document_path']
            : ['id_doc_path', 'assignment_letter_path', 'payment_proof_path', 'refund_proof_path', 'official_document_path'];
        $table = $dataset === 'loading' ? 'loading_permits' : 'work_permits';

        return DB::table($table)->whereIn('id', $ids)->get($fields)
            ->flatMap(fn ($row) => collect($fields)->map(fn (string $field) => $row->{$field} ?? null))
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->unique()->values()->all();
    }

    /** @param array<int, int> $ids */
    private function audit(string $dataset, User $admin, string $reason, string $scope, int $count, array $ids): void
    {
        AuditLog::query()->create([
            'actor_id' => $admin->id,
            'event' => 'data_deleted',
            'auditable_type' => self::class,
            'auditable_id' => null,
            'route_name' => request()->route()?->getName(),
            'request_method' => request()->method(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => [],
            'new_values' => ['dataset' => $dataset, 'scope' => $scope, 'count' => $count, 'ids' => $ids, 'reason' => $reason],
        ]);
    }

    /** @return array<string, string> */
    private function definition(string $dataset): array
    {
        return $this->datasets()[$dataset] ?? throw new InvalidArgumentException('Dataset tidak diizinkan.');
    }
}
