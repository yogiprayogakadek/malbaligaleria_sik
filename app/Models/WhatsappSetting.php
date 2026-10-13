<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappSetting extends Model
{
    protected $fillable = [
        'base_url',
        'api_key',
        'session',
        'timeout',
        'is_active',
        'notify_applicants',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'timeout' => 'integer',
            'is_active' => 'boolean',
            'notify_applicants' => 'boolean',
        ];
    }

    public static function current(): ?self
    {
        return self::query()->latest('id')->first();
    }
}
