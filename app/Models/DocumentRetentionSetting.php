<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRetentionSetting extends Model
{
    protected $fillable = [
        'enabled',
        'identity_days',
        'assignment_days',
        'financial_days',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'identity_days' => 'integer',
            'assignment_days' => 'integer',
            'financial_days' => 'integer',
        ];
    }

    public static function current(): self
    {
        return self::query()->find(1) ?? new self([
            'enabled' => false,
            'identity_days' => 30,
            'assignment_days' => 90,
            'financial_days' => 365,
        ]);
    }
}
