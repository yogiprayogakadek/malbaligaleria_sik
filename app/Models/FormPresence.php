<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormPresence extends Model
{
    protected $fillable = [
        'form_type',
        'visitor_hash',
        'tab_hash',
        'last_seen_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
