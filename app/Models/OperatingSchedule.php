<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class OperatingSchedule extends Model
{
    protected $fillable = [
        'day_of_week',
        'is_open',
        'is_24_hours',
        'opens_at',
        'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_open' => 'boolean',
            'is_24_hours' => 'boolean',
        ];
    }

    /**
     * @return Collection<int, self>
     */
    public static function weekly(): Collection
    {
        return static::query()->orderBy('day_of_week')->get()->keyBy('day_of_week');
    }

    public static function siteIsOpen(?CarbonImmutable $moment = null): bool
    {
        $now = ($moment ?? CarbonImmutable::now(config('operating-hours.timezone')))
            ->setTimezone(config('operating-hours.timezone'));
        $schedules = static::weekly();
        $today = $schedules->get($now->dayOfWeek);

        if ($today?->is_open && $today->is_24_hours) {
            return true;
        }

        $minutes = ($now->hour * 60) + $now->minute;

        if ($today?->is_open && ! $today->is_24_hours) {
            [$opens, $closes] = static::rangeInMinutes($today);

            if ($closes > $opens && $minutes >= $opens && $minutes < $closes) {
                return true;
            }

            if ($closes < $opens && $minutes >= $opens) {
                return true;
            }
        }

        $previous = $schedules->get(($now->dayOfWeek + 6) % 7);
        if ($previous?->is_open && ! $previous->is_24_hours) {
            [$opens, $closes] = static::rangeInMinutes($previous);

            return $closes < $opens && $minutes < $closes;
        }

        return false;
    }

    public static function nextOpening(?CarbonImmutable $moment = null): ?CarbonImmutable
    {
        $now = ($moment ?? CarbonImmutable::now(config('operating-hours.timezone')))
            ->setTimezone(config('operating-hours.timezone'));
        $schedules = static::weekly();

        for ($offset = 0; $offset <= 7; $offset++) {
            $date = $now->startOfDay()->addDays($offset);
            $schedule = $schedules->get($date->dayOfWeek);

            if (! $schedule?->is_open) {
                continue;
            }

            $candidate = $schedule->is_24_hours
                ? $date
                : $date->setTimeFromTimeString(substr((string) $schedule->opens_at, 0, 5));

            if ($candidate->greaterThan($now)) {
                return $candidate;
            }
        }

        return null;
    }

    public static function dayLabel(int $day): string
    {
        return config("operating-hours.days.{$day}", '-');
    }

    /**
     * @return array{int, int}
     */
    private static function rangeInMinutes(self $schedule): array
    {
        [$openHour, $openMinute] = array_map('intval', explode(':', substr((string) $schedule->opens_at, 0, 5)));
        [$closeHour, $closeMinute] = array_map('intval', explode(':', substr((string) $schedule->closes_at, 0, 5)));

        return [($openHour * 60) + $openMinute, ($closeHour * 60) + $closeMinute];
    }
}
