<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StaffLoginEvent extends Model
{
    public const UPDATED_AT = null;

    public const LOGIN_SUCCESS = 'login_success';

    public const NEW_DEVICE = 'new_device';

    public const FAILED_ATTEMPTS = 'failed_attempts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ip_address' => 'encrypted',
            'user_agent' => 'encrypted',
            'attempt_count' => 'integer',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Riwayat keamanan login tidak dapat diubah.'));
        static::deleting(fn () => throw new LogicException('Riwayat keamanan login tidak dapat dihapus.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function title(): string
    {
        return match ($this->event) {
            self::NEW_DEVICE => 'Login dari perangkat baru',
            self::FAILED_ATTEMPTS => 'Percobaan login berulang',
            default => 'Login berhasil',
        };
    }
}
