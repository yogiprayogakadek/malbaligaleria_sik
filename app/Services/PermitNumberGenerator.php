<?php

namespace App\Services;

use App\Models\LoadingPermit;
use App\Models\PermitNumberSetting;
use App\Models\WorkPermit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PermitNumberGenerator
{
    /** @var array<int, string> */
    private const ROMAN_MONTHS = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    public function nextLoadingNumber(): string
    {
        return $this->next('loading');
    }

    public function nextWorkNumber(): string
    {
        return $this->next('work');
    }

    public function preview(PermitNumberSetting $setting, int $sequence = 1): string
    {
        return strtr($setting->pattern, [
            '{month}' => now()->format('m'),
            '{month_roman}' => self::ROMAN_MONTHS[now()->month],
            '{year}' => now()->format('Y'),
            '{year_short}' => now()->format('y'),
            '{sequence}' => str_pad((string) $sequence, $setting->sequence_padding, '0', STR_PAD_LEFT),
        ]);
    }

    public function validatePattern(string $type, string $pattern, int $padding): string
    {
        preg_match_all('/\{[a-z_]+\}/', $pattern, $matches);
        $placeholders = $matches[0] ?? [];
        $allowed = ['{month}', '{month_roman}', '{year}', '{year_short}', '{sequence}'];
        $unsupported = array_diff(array_unique($placeholders), $allowed);

        if ($unsupported !== []) {
            throw ValidationException::withMessages(['pattern' => 'Placeholder tidak didukung: '.implode(', ', $unsupported).'.']);
        }
        if (substr_count($pattern, '{sequence}') !== 1) {
            throw ValidationException::withMessages(['pattern' => 'Pola wajib memiliki tepat satu placeholder {sequence}.']);
        }
        if (preg_replace('/\{[a-z_]+\}/', '', $pattern) !== '' && ! preg_match('/^[A-Za-z0-9\/_. -]+$/', preg_replace('/\{[a-z_]+\}/', '', $pattern))) {
            throw ValidationException::withMessages(['pattern' => 'Pola mengandung karakter yang tidak diizinkan.']);
        }

        $setting = new PermitNumberSetting([
            'permit_type' => $type,
            'pattern' => $pattern,
            'sequence_padding' => $padding,
            'reset_period' => 'global',
        ]);
        $preview = $this->preview($setting, 1);
        $limit = $type === 'loading' ? 40 : 60;
        if (mb_strlen($preview) > $limit) {
            throw ValidationException::withMessages(['pattern' => "Hasil nomor melebihi batas {$limit} karakter."]);
        }

        return $preview;
    }

    private function next(string $type): string
    {
        $setting = PermitNumberSetting::for($type);
        $period = $this->period($setting->reset_period);

        return DB::transaction(function () use ($type, $setting, $period): string {
            $counter = DB::table('permit_number_sequences')
                ->where('permit_type', $type)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (! $counter) {
                DB::table('permit_number_sequences')->insertOrIgnore([
                    'permit_type' => $type,
                    'period' => $period,
                    'current_value' => $this->sequenceSeed($type, $setting),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $counter = DB::table('permit_number_sequences')
                    ->where('permit_type', $type)
                    ->where('period', $period)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $sequence = (int) $counter->current_value;
            do {
                $sequence++;
                $number = $this->preview($setting, $sequence);
            } while ($this->numberExists($type, $number));

            DB::table('permit_number_sequences')->where('id', $counter->id)->update([
                'current_value' => $sequence,
                'updated_at' => now(),
            ]);

            return $number;
        }, 3);
    }

    private function period(string $resetPeriod): string
    {
        return match ($resetPeriod) {
            'monthly' => 'month-'.now()->format('m'),
            'yearly' => 'year-'.now()->format('Y'),
            default => 'global',
        };
    }

    private function numberExists(string $type, string $number): bool
    {
        $model = $type === 'loading' ? LoadingPermit::class : WorkPermit::class;

        return $model::query()->where('permit_number', $number)->exists();
    }

    private function sequenceSeed(string $type, PermitNumberSetting $setting): int
    {
        $marker = '__MBG_SEQUENCE__';
        $rendered = strtr(str_replace('{sequence}', $marker, $setting->pattern), [
            '{month}' => now()->format('m'),
            '{month_roman}' => self::ROMAN_MONTHS[now()->month],
            '{year}' => now()->format('Y'),
            '{year_short}' => now()->format('y'),
        ]);
        [$prefix, $suffix] = array_pad(explode($marker, $rendered, 2), 2, '');
        $model = $type === 'loading' ? LoadingPermit::class : WorkPermit::class;
        $numbers = $model::query()
            ->where('permit_number', 'like', $prefix.'%'.$suffix)
            ->pluck('permit_number');
        $expression = '/^'.preg_quote($prefix, '/').'([0-9]+)'.preg_quote($suffix, '/').'$/';
        $matched = $numbers->map(function (string $number) use ($expression): int {
            return preg_match($expression, $number, $matches) === 1 ? (int) $matches[1] : 0;
        })->max() ?? 0;

        $legacyGlobalSeed = $type === 'work' && $setting->reset_period === 'global'
            ? (int) ($model::query()->max('id') ?? 0)
            : 0;

        return max($matched, $legacyGlobalSeed);
    }
}
