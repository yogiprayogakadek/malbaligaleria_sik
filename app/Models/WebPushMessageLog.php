<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebPushMessageLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'failure_reason' => 'encrypted',
            'payload' => 'encrypted:array',
            'options' => 'encrypted:array',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
