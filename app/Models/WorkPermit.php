<?php

namespace App\Models;

use App\Services\PermitNumberGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use LogicException;

class WorkPermit extends Model
{
    public const WORK_CATEGORIES = [
        'general_cleaning' => 'General Cleaning',
        'flyer_distribution' => 'Sebar Flyer',
        'stock_opname' => 'Stock Opname',
        'pest_control' => 'Pest Control',
        'kol_event' => 'KOL Event',
        'grand_opening' => 'Grand Opening',
        'other' => 'Pekerjaan Lainnya',
    ];

    public const TR_CATEGORIES = [
        'general_cleaning',
        'flyer_distribution',
        'stock_opname',
        'pest_control',
    ];

    public const EP_CATEGORIES = [
        'kol_event',
        'grand_opening',
    ];

    public const SEPARATE_EVENT_CATEGORIES = [
        'kol_event',
        'grand_opening',
    ];

    protected $fillable = [
        'permit_number',
        'public_token',
        'applicant_token',
        'user_id',
        'tenant_id',
        'submission_token_hash',
        'contractor_name',
        'tenant_name',
        'applicant_name',
        'applicant_phone',
        'applicant_email',
        'work_location',
        'work_category',
        'assigned_division',
        'work_type',
        'other_work_type',
        'other_work_description',
        'includes_loading_material',
        'is_rooftop_work',
        'rooftop_network_type',
        'is_atm_repair',
        'work_schedule',
        'work_schedules',
        'start_date',
        'end_date',
        'needs_water',
        'needs_lighting',
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
        'revoked_at',
        'revoked_by',
        'revocation_reason',
        'notes',
        'id_doc_path',
        'id_doc_type',
        'assignment_letter_path',
        'assignment_letter_type',
        'status',
        'revision_return_status',
        'assigned_validator_id',
        'assigned_at',
        'assignment_note',
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
            'needs_lighting' => 'boolean',
            'security_deposit' => 'boolean',
            'includes_loading_material' => 'boolean',
            'is_rooftop_work' => 'boolean',
            'is_atm_repair' => 'boolean',
            'deposit_required' => 'boolean',
            'deposit_amount' => 'decimal:2',
            'deposit_set_at' => 'datetime',
            'payment_submitted_at' => 'datetime',
            'payment_verified_at' => 'datetime',
            'completed_at' => 'datetime',
            'refund_amount' => 'decimal:2',
            'refund_processed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'revoked_at' => 'datetime',
            'official_document_issued_at' => 'datetime',
            'assigned_at' => 'datetime',
            'sla_started_at' => 'datetime',
            'sla_escalated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkPermit $permit): void {
            $permit->public_token ??= Str::random(64);
            $permit->applicant_token ??= Str::random(64);
            $permit->permit_number ??= app(PermitNumberGenerator::class)->nextWorkNumber();
            if ($permit->requiresStaffSlaAction()) {
                $permit->sla_started_at ??= now();
            }
        });

        static::updating(function (WorkPermit $permit): void {
            if ($permit->getOriginal('official_document_id') !== null
                && $permit->isDirty(['official_document_id', 'official_document_path', 'official_document_sha256', 'official_document_issued_at'])) {
                throw new LogicException('Metadata dokumen resmi tidak dapat diubah setelah diterbitkan.');
            }

            if (! $permit->isDirty(['status', 'deposit_required', 'assigned_division'])) {
                return;
            }

            if ($permit->requiresStaffSlaAction()) {
                $permit->assigned_validator_id = null;
                $permit->assigned_at = null;
                $permit->assignment_note = null;
                $permit->sla_started_at = now();
                $permit->sla_escalated_at = null;
                $permit->sla_escalated_status = null;

                return;
            }

            $permit->assigned_validator_id = null;
            $permit->assigned_at = null;
            $permit->assignment_note = null;
            $permit->sla_started_at = null;
            $permit->sla_escalated_at = null;
            $permit->sla_escalated_status = null;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function workers(): HasMany
    {
        return $this->hasMany(WorkPermitWorker::class)->orderBy('position');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_validator_id');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(WorkPermitStatusLog::class)->latest();
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(PermitRevision::class, 'revisable');
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

    public function scopeNeedsEpAction(Builder $query): Builder
    {
        return $query->where('assigned_division', 'EP')->where('status', 'ep_review');
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
        if ($this->revoked_at) {
            return 'Dicabut';
        }

        return match ($this->status) {
            'tr_review' => 'Pemeriksaan TR',
            'mep_review' => 'Pemeriksaan Engineering',
            'ep_review' => 'Pemeriksaan EP',
            'awaiting_payment' => 'Menunggu Pembayaran',
            'payment_review' => 'Verifikasi Finance',
            'payment_revision' => 'Revisi Bukti Pembayaran',
            'revision_required' => 'Perlu Diperbaiki',
            'mep_final_review' => 'Pemeriksaan Akhir Engineering',
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
        return WorkPermitCategory::query()->where('code', $this->work_category)->value('name')
            ?? self::WORK_CATEGORIES[$this->work_category]
            ?? 'Pekerjaan Lainnya';
    }

    public static function categoryOptions(): array
    {
        return WorkPermitCategory::query()
            ->where('is_active', true)
            ->whereNotIn('code', self::SEPARATE_EVENT_CATEGORIES)
            ->orderBy('sort_order')
            ->pluck('name', 'code')
            ->all();
    }

    public static function categorySettings(): array
    {
        return WorkPermitCategory::query()
            ->where('is_active', true)
            ->whereNotIn('code', self::SEPARATE_EVENT_CATEGORIES)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (WorkPermitCategory $category): array => [
                $category->code => [
                    'division' => $category->division,
                    'max_duration_days' => $category->max_duration_days,
                ],
            ])->all();
    }

    public static function divisionForCategory(string $category): string
    {
        $configured = WorkPermitCategory::query()
            ->where('code', $category)
            ->where('is_active', true)
            ->value('division');

        if (is_string($configured)) {
            return $configured;
        }

        return match (true) {
            in_array($category, self::TR_CATEGORIES, true) => 'TR',
            in_array($category, self::EP_CATEGORIES, true) => 'EP',
            default => 'MEP',
        };
    }

    public function getWorkScheduleLabelAttribute(): string
    {
        $labels = collect($this->work_schedules ?: [$this->work_schedule])
            ->map(fn (string $schedule): ?string => match ($schedule) {
                'inside_store' => 'Dalam toko, pukul 22.00 - 10.00 WITA',
                'outside_store' => 'Di luar toko, pukul 08.00 - 16.00 WITA',
                default => null,
            })
            ->filter();

        return $labels->isEmpty() ? '-' : $labels->implode('; ');
    }

    public function requiresStaffSlaAction(): bool
    {
        if (in_array($this->status, ['tr_review', 'mep_review', 'ep_review', 'payment_review', 'mep_final_review', 'refund_processing'], true)) {
            return true;
        }

        return $this->status === 'completed' && (bool) $this->deposit_required;
    }

    public function slaDivision(): string
    {
        if ($this->status === 'payment_review') {
            return 'FIN';
        }

        return match ($this->status) {
            'tr_review' => 'TR',
            'ep_review' => 'EP',
            default => 'MEP',
        };
    }

    public function getRooftopNetworkTypeLabelAttribute(): ?string
    {
        return match ($this->rooftop_network_type) {
            'internet_installation' => 'Pemasangan internet',
            'bts_repair' => 'Perbaikan BTS',
            'ac_cleaning' => 'Maintenance AC',
            default => null,
        };
    }

    public function getOtherWorkTypeLabelAttribute(): ?string
    {
        return match ($this->other_work_type) {
            'loading_material' => 'Loading / unloading material',
            'rooftop' => 'Rooftop: '.($this->rooftop_network_type_label ?: 'Pekerjaan rooftop'),
            'atm_repair' => 'Perbaikan ATM',
            'fit_out' => 'Fit Out / Renovasi',
            'demolition' => 'Pembongkaran',
            'other' => $this->other_work_description ?: 'Lainnya',
            default => null,
        };
    }

    public function requiresAssignmentLetter(): bool
    {
        return $this->work_category === 'other' && ($this->is_rooftop_work || $this->is_atm_repair);
    }

    public function actionDivision(): ?string
    {
        return match ($this->status) {
            'tr_review' => 'TR',
            'ep_review' => 'EP',
            'mep_review', 'mep_final_review', 'refund_processing' => 'MEP',
            'approved' => $this->assigned_division === 'MEP' ? 'MEP' : null,
            'completed' => $this->assigned_division === 'MEP' && $this->deposit_required ? 'MEP' : null,
            'payment_review' => 'FIN',
            default => null,
        };
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
