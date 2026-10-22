<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTemplate extends Model
{
    public const PERMIT_TYPES = ['loading', 'work', 'event'];

    protected $fillable = [
        'permit_type',
        'version',
        'original_name',
        'storage_path',
        'sha256',
        'size',
        'placeholders',
        'uploaded_by',
        'is_active',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'size' => 'integer',
            'placeholders' => 'array',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public static function activeFor(string $permitType): ?self
    {
        return self::query()
            ->where('permit_type', $permitType)
            ->where('is_active', true)
            ->latest('version')
            ->first();
    }

    public function typeLabel(): string
    {
        return match ($this->permit_type) {
            'loading' => 'Loading / unloading',
            'work' => 'Surat izin kerja',
            'event' => 'Surat izin event',
            default => $this->permit_type,
        };
    }
}
