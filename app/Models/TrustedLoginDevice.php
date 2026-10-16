<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustedLoginDevice extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'last_ip' => 'encrypted',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
