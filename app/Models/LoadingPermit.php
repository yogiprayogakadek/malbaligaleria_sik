<?php

namespace App\Models;

use App\Services\PermitNumberGenerator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class LoadingPermit extends Model
{
    protected $fillable = [
        'permit_number',
        'user_id',
        'submission_token_hash',
        'tenant_name',
        'applicant_name',
        'applicant_phone',
        'applicant_email',
        'direction',
        'start_date',
        'end_date',
        'movement_time',
        'item_count',
        'item_unit',
        'item_description',
        'vehicle_plate',
        'id_doc_path',
        'id_doc_type',
        'status',
        'revision_return_status',
        'assigned_validator_id',
        'assigned_at',
        'assignment_note',
        'reviewed_by',
        'review_notes',
        'reviewed_at',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
        'barcode_token',
        'barcode_expires_at',
        'sla_started_at',
        'sla_escalated_at',
        'sla_escalated_status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'reviewed_at' => 'datetime',
        'revoked_at' => 'datetime',
        'assigned_at' => 'datetime',
        'barcode_expires_at' => 'datetime',
        'sla_started_at' => 'datetime',
        'sla_escalated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (LoadingPermit $permit): void {
            $permit->document_token ??= Str::random(64);
            if (($permit->status ?? 'pending') === 'pending') {
                $permit->sla_started_at ??= now();
            }
        });

        static::updating(function (LoadingPermit $permit): void {
            if (! $permit->isDirty('status')) {
                return;
            }

            if ($permit->status === 'pending') {
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

    // ─── Relations ────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function notifications(): HasMany
    {
        return $this->hasMany(PermitNotification::class, 'permit_id');
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(PermitRevision::class, 'revisable');
    }

    public function scopeSlaPriority(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN sla_started_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sla_started_at')
            ->orderByDesc('id');
    }

    // ─── Static Helpers ───────────────────────────────────────────────────────

    public static function generatePermitNumber(): string
    {
        return app(PermitNumberGenerator::class)->nextLoadingNumber();
    }

    /**
     * Hasilkan barcode token unik dan simpan ke record ini.
     * Token dibuat dari UUID + timestamp + permit ID (tidak bisa ditebak).
     */
    public function generateBarcodeToken(): string
    {
        $token = hash('sha256', Str::uuid().$this->id.now()->timestamp.Str::random(16));
        $this->update([
            'barcode_token' => $token,
            'barcode_expires_at' => $this->end_date->copy()->endOfDay(),
        ]);

        return $token;
    }

    // ─── Computed Attributes ──────────────────────────────────────────────────

    public function getDirectionLabelAttribute(): string
    {
        return match ($this->direction) {
            'in' => 'Barang Masuk',
            'out' => 'Barang Keluar',
            'both' => 'Masuk & Keluar',
            default => '-',
        };
    }

    public function getMovementTimeFieldLabelAttribute(): string
    {
        return match ($this->direction) {
            'in' => 'Waktu Unloading',
            'out' => 'Waktu Loading',
            'both' => 'Waktu Loading & Unloading',
            default => 'Waktu Loading / Unloading',
        };
    }

    public function getMovementTimeLabelAttribute(): string
    {
        return $this->movement_time
            ? substr((string) $this->movement_time, 0, 5).' WITA'
            : '-';
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->revoked_at) {
            return 'Dicabut';
        }

        return match ($this->status) {
            'pending' => 'Menunggu Verifikasi',
            'revision_required' => 'Perlu Diperbaiki',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => '-',
        };
    }

    public function requiresStaffSlaAction(): bool
    {
        return $this->status === 'pending';
    }

    public function getSlaDueAtAttribute(): mixed
    {
        return $this->sla_started_at?->copy()->addHours(SlaSetting::hoursFor('loading'));
    }

    public function getIsSlaOverdueAttribute(): bool
    {
        return $this->requiresStaffSlaAction()
            && $this->sla_due_at !== null
            && now()->greaterThan($this->sla_due_at);
    }

    public function isExpired(): bool
    {
        $validUntil = $this->barcodeValidUntil();

        if (! $validUntil) {
            return true;
        }

        return now()->isAfter($validUntil);
    }

    public function barcodeValidUntil(): ?CarbonInterface
    {
        if (! $this->barcode_expires_at || ! $this->end_date) {
            return null;
        }

        return $this->end_date->copy()->endOfDay();
    }

    public function isActive(): bool
    {
        return $this->status === 'approved' && ! $this->revoked_at && ! $this->isExpired();
    }
}
