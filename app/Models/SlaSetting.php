<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SlaSetting extends Model
{
    private const CACHE_KEY = 'settings:permit-sla';

    protected $fillable = [
        'loading_hours',
        'work_hours',
    ];

    protected function casts(): array
    {
        return [
            'loading_hours' => 'integer',
            'work_hours' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public static function current(): self
    {
        return self::query()->find(1) ?? new self([
            'loading_hours' => 8,
            'work_hours' => 24,
        ]);
    }

    public static function hoursFor(string $permitType): int
    {
        $limits = Cache::remember(self::CACHE_KEY, now()->addMinute(), function (): array {
            $setting = self::current();

            return [
                'loading' => $setting->loading_hours,
                'work' => $setting->work_hours,
            ];
        });

        return (int) ($limits[$permitType] ?? 24);
    }
}
