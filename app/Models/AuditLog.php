<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ip_address' => 'encrypted',
            'user_agent' => 'encrypted',
            'old_values' => 'encrypted:array',
            'new_values' => 'encrypted:array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log tidak dapat diubah.'));
        static::deleting(fn () => throw new LogicException('Audit log tidak dapat dihapus.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
