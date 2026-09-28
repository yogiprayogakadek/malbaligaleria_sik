<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkPermitStatusLog extends Model
{
    protected $fillable = [
        'work_permit_id',
        'actor_id',
        'from_status',
        'to_status',
        'action',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function permit(): BelongsTo
    {
        return $this->belongsTo(WorkPermit::class, 'work_permit_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
