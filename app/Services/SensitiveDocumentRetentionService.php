<?php

namespace App\Services;

use App\Models\DocumentRetentionSetting;
use App\Models\LoadingPermit;
use App\Models\WorkPermit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SensitiveDocumentRetentionService
{
    private const LOADING_DOCUMENTS = [
        'id_doc_path' => ['prefix' => 'permits/id-docs/', 'retention' => 'identity_days'],
        'instruction_letter_path' => ['prefix' => 'permits/instruction-letters/', 'retention' => 'assignment_days'],
    ];

    private const WORK_DOCUMENTS = [
        'id_doc_path' => ['prefix' => 'permits/work/id-docs/', 'retention' => 'identity_days'],
        'assignment_letter_path' => ['prefix' => 'permits/work/assignment-letters/', 'retention' => 'assignment_days'],
        'payment_proof_path' => ['prefix' => 'permits/work/payment-proofs/', 'retention' => 'financial_days'],
        'refund_proof_path' => ['prefix' => 'permits/work/refund-proofs/', 'retention' => 'financial_days'],
    ];

    /** @return array{eligible: int, purged: int, missing: int, failed: int} */
    public function run(bool $dryRun = false): array
    {
        $setting = DocumentRetentionSetting::current();
        $result = ['eligible' => 0, 'purged' => 0, 'missing' => 0, 'failed' => 0];

        LoadingPermit::query()
            ->whereNotNull('id_doc_path')
            ->whereIn('status', ['approved', 'rejected'])
            ->chunkById(100, function ($permits) use ($setting, $dryRun, &$result): void {
                foreach ($permits as $permit) {
                    $this->process($permit, self::LOADING_DOCUMENTS, $setting, $dryRun, $result);
                }
            });

        WorkPermit::query()
            ->where(function ($query): void {
                foreach (array_keys(self::WORK_DOCUMENTS) as $field) {
                    $query->orWhereNotNull($field);
                }
            })
            ->whereIn('status', ['approved', 'completed', 'refunded', 'rejected'])
            ->chunkById(100, function ($permits) use ($setting, $dryRun, &$result): void {
                foreach ($permits as $permit) {
                    $this->process($permit, self::WORK_DOCUMENTS, $setting, $dryRun, $result);
                }
            });

        return $result;
    }

    /**
     * @param  array<string, array{prefix: string, retention: string}>  $documents
     * @param  array{eligible: int, purged: int, missing: int, failed: int}  $result
     */
    private function process(Model $permit, array $documents, DocumentRetentionSetting $setting, bool $dryRun, array &$result): void
    {
        $changed = false;

        foreach ($documents as $field => $policy) {
            $path = $permit->getAttribute($field);
            if (! is_string($path) || $path === '') {
                continue;
            }

            $eligibleAt = $this->eligibleAt($permit, $field);
            $retentionDays = (int) $setting->getAttribute($policy['retention']);
            if ($eligibleAt === null || now()->lt($eligibleAt->copy()->addDays($retentionDays))) {
                continue;
            }

            $result['eligible']++;
            if ($dryRun) {
                continue;
            }

            if (! $this->isAllowedPath($path, $policy['prefix'])) {
                $result['failed']++;
                Log::error('Path dokumen retensi berada di luar direktori yang diizinkan.', $this->context($permit, $field));

                continue;
            }

            try {
                if (Storage::disk('local')->exists($path)) {
                    if (! Storage::disk('local')->delete($path)) {
                        $result['failed']++;
                        Log::error('Dokumen retensi gagal dihapus dari storage.', $this->context($permit, $field));

                        continue;
                    }
                } else {
                    $result['missing']++;
                }

                $permit->setAttribute($field, null);
                $changed = true;
                $result['purged']++;
            } catch (Throwable $exception) {
                $result['failed']++;
                Log::error('Penghapusan dokumen retensi mengalami kegagalan.', array_merge(
                    $this->context($permit, $field),
                    ['exception' => $exception::class],
                ));
            }
        }

        if ($changed) {
            $permit->save();
        }
    }

    private function eligibleAt(Model $permit, string $field): ?CarbonInterface
    {
        if ($permit instanceof LoadingPermit) {
            return $permit->status === 'rejected'
                ? ($permit->reviewed_at ?? $permit->updated_at)
                : $permit->end_date?->copy()->endOfDay();
        }

        if (! $permit instanceof WorkPermit) {
            return null;
        }

        if (in_array($field, ['payment_proof_path', 'refund_proof_path'], true)) {
            return match ($permit->status) {
                'rejected' => $permit->reviewed_at ?? $permit->updated_at,
                'refunded' => $permit->refund_processed_at ?? $permit->updated_at,
                default => null,
            };
        }

        return match ($permit->status) {
            'rejected' => $permit->reviewed_at ?? $permit->updated_at,
            'refunded' => $permit->refund_processed_at ?? $permit->updated_at,
            'completed' => $permit->deposit_required
                ? null
                : ($permit->completed_at ?? $permit->end_date?->copy()->endOfDay()),
            'approved' => $permit->deposit_required
                ? null
                : $permit->end_date?->copy()->endOfDay(),
            default => null,
        };
    }

    private function isAllowedPath(string $path, string $prefix): bool
    {
        if (! str_starts_with($path, $prefix)) {
            return false;
        }

        $filename = substr($path, strlen($prefix));

        return $filename !== ''
            && ! str_contains($filename, '..')
            && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/D', $filename) === 1;
    }

    /** @return array{permit_type: string, permit_id: mixed, field: string} */
    private function context(Model $permit, string $field): array
    {
        return [
            'permit_type' => $permit instanceof LoadingPermit ? 'loading' : 'work',
            'permit_id' => $permit->getKey(),
            'field' => $field,
        ];
    }
}
