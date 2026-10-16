<?php

namespace App\Services;

use App\Models\LoadingPermit;
use App\Models\WorkPermit;
use Illuminate\Support\Facades\DB;

class PermitNumberGenerator
{
    /** @var array<int, string> */
    private const ROMAN_MONTHS = [
        1 => 'I',
        2 => 'II',
        3 => 'III',
        4 => 'IV',
        5 => 'V',
        6 => 'VI',
        7 => 'VII',
        8 => 'VIII',
        9 => 'IX',
        10 => 'X',
        11 => 'XI',
        12 => 'XII',
    ];

    public function nextLoadingNumber(): string
    {
        $month = now()->month;
        $romanMonth = self::ROMAN_MONTHS[$month];
        $prefix = "MBG/SIK/{$romanMonth}/";
        $period = sprintf('month-%02d', $month);

        $sequence = DB::transaction(function () use ($prefix, $period): int {
            $counter = DB::table('permit_number_sequences')
                ->where('permit_type', 'loading')
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (! $counter) {
                $seed = LoadingPermit::query()
                    ->where('permit_number', 'like', $prefix.'%')
                    ->pluck('permit_number')
                    ->map(function (string $number) use ($prefix): int {
                        $suffix = substr($number, strlen($prefix));

                        return ctype_digit($suffix) ? (int) $suffix : 0;
                    })
                    ->max() ?? 0;

                DB::table('permit_number_sequences')->insertOrIgnore([
                    'permit_type' => 'loading',
                    'period' => $period,
                    'current_value' => $seed,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $counter = DB::table('permit_number_sequences')
                    ->where('permit_type', 'loading')
                    ->where('period', $period)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $next = (int) $counter->current_value + 1;
            DB::table('permit_number_sequences')
                ->where('id', $counter->id)
                ->update([
                    'current_value' => $next,
                    'updated_at' => now(),
                ]);

            return $next;
        }, 3);

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function nextWorkNumber(): string
    {
        $month = now()->month;
        $romanMonth = self::ROMAN_MONTHS[$month];
        $year = now()->year;
        $prefix = "MBG/SIK/{$romanMonth}/{$year}/";

        $sequence = DB::transaction(function (): int {
            $counter = DB::table('permit_number_sequences')
                ->where('permit_type', 'work')
                ->where('period', 'global')
                ->lockForUpdate()
                ->first();

            if (! $counter) {
                DB::table('permit_number_sequences')->insertOrIgnore([
                    'permit_type' => 'work',
                    'period' => 'global',
                    'current_value' => WorkPermit::query()->max('id') ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $counter = DB::table('permit_number_sequences')
                    ->where('permit_type', 'work')
                    ->where('period', 'global')
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $next = (int) $counter->current_value + 1;
            DB::table('permit_number_sequences')
                ->where('id', $counter->id)
                ->update([
                    'current_value' => $next,
                    'updated_at' => now(),
                ]);

            return $next;
        }, 3);

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
