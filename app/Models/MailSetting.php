<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    protected $fillable = [
        'host',
        'port',
        'scheme',
        'username',
        'password',
        'from_address',
        'from_name',
        'timeout',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'port' => 'integer',
            'timeout' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function current(): ?self
    {
        return self::query()->latest('id')->first();
    }
}
