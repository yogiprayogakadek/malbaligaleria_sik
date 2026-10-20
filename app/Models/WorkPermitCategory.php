<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkPermitCategory extends Model
{
    protected $fillable = ['code', 'name', 'division', 'max_duration_days', 'is_active', 'is_system', 'sort_order'];

    protected function casts(): array
    {
        return [
            'max_duration_days' => 'integer',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
