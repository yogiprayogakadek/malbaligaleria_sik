<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class OperatingScheduleException extends Model
{
    protected $fillable = [
        'date',
        'label',
        'is_open',
        'is_24_hours',
        'opens_at',
        'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_open' => 'boolean',
            'is_24_hours' => 'boolean',
        ];
    }

    public static function forDate(CarbonImmutable $date): ?self
    {
        return static::query()->whereDate('date', $date->toDateString())->first();
    }
}
