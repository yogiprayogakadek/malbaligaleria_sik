<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadSecuritySetting extends Model
{
    protected $fillable = [
        'malware_scanning_enabled',
        'disabled_reason',
        'updated_by',
    ];

    protected function casts(): array
    {
        return ['malware_scanning_enabled' => 'boolean'];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function current(): self
    {
        return self::query()->find(1) ?? new self([
            'malware_scanning_enabled' => true,
        ]);
    }

    public static function scanningEnabled(): bool
    {
        return (bool) config('malware.enabled') && self::current()->malware_scanning_enabled;
    }
}
