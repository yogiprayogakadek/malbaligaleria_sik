<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PermitRevision extends Model
{
    protected $fillable = [
        'batch_id',
        'requested_by',
        'field_key',
        'reason',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function revisable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
