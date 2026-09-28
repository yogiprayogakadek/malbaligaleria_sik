<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkPermitWorker extends Model
{
    protected $fillable = [
        'name',
        'identity_number',
        'position',
    ];

    public function workPermit(): BelongsTo
    {
        return $this->belongsTo(WorkPermit::class);
    }
}
