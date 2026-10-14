<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WorkPermit extends Model
{
    public const WORK_CATEGORIES = [
        'general_cleaning' => 'General Cleaning',
        'flyer_distribution' => 'Sebar Flyer',
        'stock_opname' => 'Stock Opname',
        'pest_control' => 'Pest Control',
        'other' => 'Pekerjaan Lainnya',
    ];

    public const TR_CATEGORIES = [
        'general_cleaning',
        'flyer_distribution',
        'stock_opname',
        'pest_control',
    ];

    protected $fillable = [
        'permit_number',
        'public_token',
        'applicant_token',
        'user_id',
        'contractor_name',
        'applicant_name',
        'applicant_phone',
        'applicant_email',
        'work_location',
        'work_category',
        'assigned_division',
        'work_type',
        'work_schedule',
        'work_schedules',
        'start_date',
        'end_date',
        'needs_water',
        'security_deposit',
        'deposit_required',
        'deposit_amount',
        'deposit_notes',
        'deposit_set_by',
        'deposit_set_at',
        'payment_proof_path',
        'payment_proof_type',
        'payment_submitted_at',
        'payment_verified_by',
        'payment_verified_at',
        'finance_notes',
        'completed_by',
        'completed_at',
        'refund_amount',
        'refund_proof_path',
        'refund_proof_type',
        'refund_notes',
        'refund_processed_by',
        'refund_processed_at',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
        'notes',
        'id_doc_path',
        'id_doc_type',
        'status',
        'sla_started_at',
        'sla_escalated_at',
        'sla_escalated_status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'work_schedules' => 'array',
            'needs_water' => 'boolean',
            'security_deposit' => 'boolean',
            'deposit_required' => 'boolean',
            'deposit_amount' => 'decimal:2',
            'deposit_set_at' => 'datetime',
            'payment_submitted_at' => 'datetime',
            'payment_verified_at' => 'datetime',
            'completed_at' => 'datetime',
            'refund_amount' => 'decimal:2',
            'refund_processed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'sla_started_at' => 'datetime',
            'sla_escalated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkPermit $permit): void {
            $permit->public_token ??= Str::random(64);
            $permit->applicant_token ??= Str::random(64);
            $permit->permit_number ??= sprintf(
                'MBG/SIK-KERJA/%s/%s',
                now()->format('Ym'),
                Str::upper(Str::random(8)),
            );
            if ($permit->requiresStaffSlaAction()) {
                $permit->sla_started_at ??= now();
            }
        });

        static::updating(function (WorkPermit $permit): void {
            if (! $permit->isDirty(['status', 'deposit_required', 'assigned_division'])) {
                return;
            }

            if ($permit->requiresStaffSlaAction()) {
                $permit->sla_started_at = now();
                $permit->sla_escalated_at = null;
                $permit->sla_escalated_status = null;

                return;
            }

            $permit->sla_started_at = null;
            $permit->sla_escalated_at = null;
            $permit->sla_escalated_status = null;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workers(): HasMany
    {
        return $this->hasMany(WorkPermitWorker::class)->orderBy('position');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(WorkPermitStatusLog::class)->latest();
    }

    public function scopeNeedsMepAction(Builder $query): Builder
    {
        return $query->where('assigned_division', 'MEP')->where(function (Builder $statusQuery): void {
            $statusQuery
                ->whereIn('status', ['mep_review', 'mep_final_review', 'refund_processing'])
                ->orWhere(function (Builder $completedQuery): void {
                    $completedQuery->where('status', 'completed')->where('deposit_required', true);
                });
        });
    }

    public function scopeNeedsTrAction(Builder $query): Builder
    {
        return $query->where('assigned_division', 'TR')->where('status', 'tr_review');
    }

    public function scopeSlaPriority(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN sla_started_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sla_started_at')
            ->orderByDesc('id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'tr_review' => 'Pemeriksaan TR',
            'mep_review' => 'Pemeriksaan MEP',
            'awaiting_payment' => 'Menunggu Pembayaran',
            'payment_review' => 'Verifikasi Finance',
            'payment_revision' => 'Revisi Bukti Pembayaran',
            'mep_final_review' => 'Pemeriksaan Akhir MEP',
            'approved' => 'Disetujui',
            'completed' => 'Pekerjaan Selesai',
            'refund_processing' => 'Pengembalian Diproses',
            'refunded' => 'Deposit Dikembalikan',
            'rejected' => 'Ditolak',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function getWorkCategoryLabelAttribute(): string
    {
        return self::WORK_CATEGORIES[$this->work_category] ?? 'Pekerjaan Lainnya';
    }

    public static function divisionForCategory(string $category): string
    {
        return in_array($category, self::TR_CATEGORIES, true) ? 'TR' : 'MEP';
    }

    public function getWorkScheduleLabelAttribute(): string
    {
        $labels = collect($this->work_schedules ?: [$this->work_schedule])
            ->map(fn (string $schedule): ?string => match ($schedule) {
                'inside_store' => 'Dalam toko, pukul 22.00 - 11.00 WITA',
                'outside_store' => 'Di luar toko, pukul 08.00 - 16.00 WITA',
                default => null,
            })
            ->filter();

        return $labels->isEmpty() ? '-' : $labels->implode('; ');
    }

    public function requiresStaffSlaAction(): bool
    {
        if (in_array($this->status, ['tr_review', 'mep_review', 'payment_review', 'mep_final_review', 'refund_processing'], true)) {
            return true;
        }

        return $this->status === 'completed' && (bool) $this->deposit_required;
    }

    public function slaDivision(): string
    {
        if ($this->status === 'payment_review') {
            return 'FIN';
        }

        return $this->status === 'tr_review' ? 'TR' : 'MEP';
    }

    public function getSlaDueAtAttribute(): mixed
    {
        return $this->sla_started_at?->copy()->addHours(SlaSetting::hoursFor('work'));
    }

    public function getIsSlaOverdueAttribute(): bool
    {
        return $this->requiresStaffSlaAction()
            && $this->sla_due_at !== null
            && now()->greaterThan($this->sla_due_at);
    }
}
