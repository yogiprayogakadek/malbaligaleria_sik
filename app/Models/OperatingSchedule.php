<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class OperatingSchedule extends Model
{
    public const SCOPE_GENERAL = 'general';

    public const SCOPE_EVENT = 'event';

    protected $fillable = [
        'scope',
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
    public static function weekly(string $scope = self::SCOPE_GENERAL): Collection
    {
        return static::query()->where('scope', $scope)->orderBy('day_of_week')->get()->keyBy('day_of_week');
    }

    public static function siteIsOpen(?CarbonImmutable $moment = null, string $scope = self::SCOPE_GENERAL): bool
    {
        $now = ($moment ?? CarbonImmutable::now(config('operating-hours.timezone')))
            ->setTimezone(config('operating-hours.timezone'));
        $schedules = static::weekly($scope);
        [$today, $todayIsHoliday] = static::scheduleForDate($now, $scope, $schedules);

        if ($todayIsHoliday) {
            return false;
        }

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

        $previousDate = $now->subDay();
        [$previous, $previousIsHoliday] = static::scheduleForDate($previousDate, $scope, $schedules);
        if ($previousIsHoliday) {
            return false;
        }
        if ($previous?->is_open && ! $previous->is_24_hours) {
            [$opens, $closes] = static::rangeInMinutes($previous);

            return $closes < $opens && $minutes < $closes;
        }

        return false;
    }

    public static function nextOpening(?CarbonImmutable $moment = null, string $scope = self::SCOPE_GENERAL): ?CarbonImmutable
    {
        $now = ($moment ?? CarbonImmutable::now(config('operating-hours.timezone')))
            ->setTimezone(config('operating-hours.timezone'));
        $schedules = static::weekly($scope);

        for ($offset = 0; $offset <= 7; $offset++) {
            $date = $now->startOfDay()->addDays($offset);
            [$schedule, $isHoliday] = static::scheduleForDate($date, $scope, $schedules);

            if ($isHoliday || ! $schedule?->is_open) {
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
    private static function rangeInMinutes(Model $schedule): array
    {
        [$openHour, $openMinute] = array_map('intval', explode(':', substr((string) $schedule->opens_at, 0, 5)));
        [$closeHour, $closeMinute] = array_map('intval', explode(':', substr((string) $schedule->closes_at, 0, 5)));

        return [($openHour * 60) + $openMinute, ($closeHour * 60) + $closeMinute];
    }

    /**
     * @param  Collection<int, self>  $schedules
     * @return array{Model|null, bool}
     */
    private static function scheduleForDate(CarbonImmutable $date, string $scope, Collection $schedules): array
    {
        $exception = OperatingScheduleException::forDate($date, $scope);
        if ($exception) {
            return [$exception, false];
        }

        if ($scope === self::SCOPE_GENERAL && NationalHoliday::fallsOn($date)) {
            return [null, true];
        }

        return [$schedules->get($date->dayOfWeek), false];
    }
}
