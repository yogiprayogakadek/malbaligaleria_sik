<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SecurityGateEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'latitude' => 'encrypted',
            'longitude' => 'encrypted',
            'accuracy' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function securityOfficer(): BelongsTo
    {
        return $this->belongsTo(SecurityOfficer::class);
    }

    public function loadingPermit(): BelongsTo
    {
        return $this->belongsTo(LoadingPermit::class, 'permit_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Aktivitas gerbang tidak dapat diubah.'));
        static::deleting(fn () => throw new LogicException('Aktivitas gerbang tidak dapat dihapus.'));
    }
}
