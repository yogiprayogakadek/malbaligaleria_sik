<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class NationalHoliday extends Model
{
    protected $fillable = [
        'external_id',
        'date',
        'name',
        'source',
        'year',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'year' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public static function fallsOn(CarbonImmutable $date): bool
    {
        return static::query()->whereDate('date', $date->toDateString())->exists();
    }
}
