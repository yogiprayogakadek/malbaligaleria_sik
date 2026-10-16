<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecuritySetting extends Model
{
    protected $fillable = [
        'staff_two_factor_required',
        'login_alerts_enabled',
        'failed_login_alert_threshold',
    ];

    protected function casts(): array
    {
        return [
            'staff_two_factor_required' => 'boolean',
            'login_alerts_enabled' => 'boolean',
            'failed_login_alert_threshold' => 'integer',
        ];
    }

    public static function current(): self
    {
        return self::query()->find(1) ?? new self([
            'staff_two_factor_required' => true,
            'login_alerts_enabled' => true,
            'failed_login_alert_threshold' => 3,
        ]);
    }

    public static function requiresStaffTwoFactor(): bool
    {
        return self::current()->staff_two_factor_required;
    }
}
