<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermitNumberSetting extends Model
{
    protected $fillable = ['permit_type', 'pattern', 'sequence_padding', 'reset_period'];

    protected function casts(): array
    {
        return ['sequence_padding' => 'integer'];
    }

    public static function for(string $type): self
    {
        return self::query()->where('permit_type', $type)->firstOrNew(
            ['permit_type' => $type],
            self::defaults($type),
        );
    }

    /** @return array<string, mixed> */
    public static function defaults(string $type): array
    {
        return $type === 'loading'
            ? ['pattern' => 'MBG/SIK/{month_roman}/{sequence}', 'sequence_padding' => 4, 'reset_period' => 'monthly']
            : ['pattern' => 'MBG/SIK/{month_roman}/{year}/{sequence}', 'sequence_padding' => 4, 'reset_period' => 'global'];
    }
}
