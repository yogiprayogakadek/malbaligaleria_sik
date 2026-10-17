<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScannerIpBlock extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ip_address' => 'encrypted',
            'blocked_until' => 'datetime',
            'last_alerted_at' => 'datetime',
            'unblocked_at' => 'datetime',
        ];
    }

    public function unblockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unblocked_by');
    }

    public function isActive(): bool
    {
        return $this->unblocked_at === null
            && $this->blocked_until !== null
            && $this->blocked_until->isFuture();
    }
}
