<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecuritySetting extends Model
{
    protected $fillable = [
        'staff_two_factor_required',
    ];

    protected function casts(): array
    {
        return [
            'staff_two_factor_required' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::query()->find(1) ?? new self([
            'staff_two_factor_required' => true,
        ]);
    }

    public static function requiresStaffTwoFactor(): bool
    {
        return self::current()->staff_two_factor_required;
    }
}
