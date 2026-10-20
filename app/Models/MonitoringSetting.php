<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoringSetting extends Model
{
    protected $fillable = ['enabled', 'webhook_url', 'signing_secret', 'timeout_seconds'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'webhook_url' => 'encrypted',
            'signing_secret' => 'encrypted',
            'timeout_seconds' => 'integer',
        ];
    }

    public static function current(): self
    {
        return self::query()->find(1) ?? new self(['enabled' => false, 'timeout_seconds' => 8]);
    }
}
