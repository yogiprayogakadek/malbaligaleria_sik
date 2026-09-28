<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LoadingPermit extends Model
{
    protected $fillable = [
        'permit_number',
        'user_id',
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
        'reviewed_by',
        'review_notes',
        'reviewed_at',
        'barcode_token',
        'barcode_expires_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'reviewed_at' => 'datetime',
        'barcode_expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (LoadingPermit $permit): void {
            $permit->document_token ??= Str::random(64);
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

    public function notifications(): HasMany
    {
        return $this->hasMany(PermitNotification::class, 'permit_id');
    }

    // ─── Static Helpers ───────────────────────────────────────────────────────

    /**
     * Generate nomor surat unik: MBG/SIK/{Bulan_Romawi}/{4-digit-urutan}
     */
    public static function generatePermitNumber(): string
    {
        $romans = [
            1 => 'I',   2 => 'II',   3 => 'III', 4 => 'IV',
            5 => 'V',   6 => 'VI',   7 => 'VII', 8 => 'VIII',
            9 => 'IX',  10 => 'X',    11 => 'XI',  12 => 'XII',
        ];

        $month = (int) date('n');
        $year = date('Y');
        $roman = $romans[$month];

        // Urutan surat bulan ini
        $count = static::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count() + 1;

        return sprintf('MBG/SIK/%s/%04d', $roman, $count);
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
            'barcode_expires_at' => $this->end_date->endOfDay()->addHours(24),
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
        return match ($this->status) {
            'pending' => 'Menunggu Verifikasi',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => '-',
        };
    }

    public function isExpired(): bool
    {
        if (! $this->barcode_expires_at) {
            return true;
        }

        return now()->isAfter($this->barcode_expires_at);
    }

    public function isActive(): bool
    {
        return $this->status === 'approved' && ! $this->isExpired();
    }
}
