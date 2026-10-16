<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PermitSubmissionLimitSetting extends Model
{
    private const CACHE_KEY = 'settings:permit-submission-limit';

    protected $fillable = [
        'enabled',
        'max_per_minute',
        'max_per_day',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'max_per_minute' => 'integer',
            'max_per_day' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public static function current(): self
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinute(), fn (): self => self::query()->find(1) ?? new self([
            'enabled' => true,
            'max_per_minute' => 10,
            'max_per_day' => 25,
        ])
        );
    }
}
