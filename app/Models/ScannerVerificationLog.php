<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ScannerVerificationLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ip_address' => 'encrypted',
            'user_agent' => 'encrypted',
            'latitude' => 'encrypted',
            'longitude' => 'encrypted',
            'accuracy' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Log scanner tidak dapat diubah.'));
        static::deleting(fn () => throw new LogicException('Log scanner tidak dapat dihapus.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
