<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AnnouncementSetting extends Model
{
    private const CACHE_KEY = 'settings:announcement-limiter';

    protected $fillable = [
        'max_attempts',
        'decay_seconds',
    ];

    protected function casts(): array
    {
        return [
            'max_attempts' => 'integer',
            'decay_seconds' => 'integer',
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
            'max_attempts' => 10,
            'decay_seconds' => 60,
        ]));
    }
}
