<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailMessageLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'recipient' => 'encrypted',
            'failure_reason' => 'encrypted',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function emailable(): MorphTo
    {
        return $this->morphTo();
    }

    public function maskedRecipient(): string
    {
        [$local, $domain] = array_pad(explode('@', $this->recipient, 2), 2, '');
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible.str_repeat('*', max(3, mb_strlen($local) - mb_strlen($visible))).($domain ? '@'.$domain : '');
    }
}
