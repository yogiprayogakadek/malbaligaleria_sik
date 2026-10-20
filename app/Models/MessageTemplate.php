<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    protected $fillable = ['key', 'name', 'channel', 'subject', 'body', 'allowed_variables', 'is_active', 'is_system'];

    protected function casts(): array
    {
        return ['allowed_variables' => 'array', 'is_active' => 'boolean', 'is_system' => 'boolean'];
    }
}
