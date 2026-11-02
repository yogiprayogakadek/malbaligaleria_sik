<?php

namespace App\Models;

use App\Services\AuditIntegrityService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
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
        static::creating(fn (AuditLog $log) => app(AuditIntegrityService::class)->prepare($log));
        static::created(fn (AuditLog $log) => app(AuditIntegrityService::class)->advance($log));
        static::updating(fn () => throw new LogicException('Audit log tidak dapat diubah.'));
        static::deleting(fn () => throw new LogicException('Audit log tidak dapat dihapus.'));
    }

    public function save(array $options = []): bool
    {
        if ($this->exists || DB::transactionLevel() > 0) {
            return parent::save($options);
        }

        return DB::transaction(fn (): bool => parent::save($options), 3);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
