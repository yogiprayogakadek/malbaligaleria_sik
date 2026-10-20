<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkPermitDurationSetting extends Model
{
    protected $fillable = ['default_days', 'fit_out_days', 'demolition_days'];

    protected function casts(): array
    {
        return [
            'default_days' => 'integer',
            'fit_out_days' => 'integer',
            'demolition_days' => 'integer',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1], [
            'default_days' => 7,
            'fit_out_days' => 30,
            'demolition_days' => 14,
        ]);
    }

    public function daysFor(?string $otherWorkType): int
    {
        return match ($otherWorkType) {
            'fit_out' => $this->fit_out_days,
            'demolition' => $this->demolition_days,
            default => $this->default_days,
        };
    }
}
