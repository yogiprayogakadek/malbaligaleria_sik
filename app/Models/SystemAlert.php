<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemAlert extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'first_detected_at' => 'datetime',
            'last_detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
