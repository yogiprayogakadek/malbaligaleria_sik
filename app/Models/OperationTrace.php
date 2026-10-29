<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationTrace extends Model
{
    protected $fillable = [
        'request_id',
        'operation',
        'route_name',
        'status',
        'response_status',
        'total_ms',
        'query_count',
        'query_ms',
        'upload_count',
        'upload_bytes',
        'slowest_step',
        'slowest_step_ms',
        'steps',
        'loading_permit_id',
        'work_permit_id',
        'actor_id',
        'started_at',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'started_at' => 'datetime',
            'total_ms' => 'integer',
            'query_count' => 'integer',
            'query_ms' => 'integer',
            'upload_count' => 'integer',
            'upload_bytes' => 'integer',
            'slowest_step_ms' => 'integer',
        ];
    }

    public function loadingPermit(): BelongsTo
    {
        return $this->belongsTo(LoadingPermit::class);
    }

    public function workPermit(): BelongsTo
    {
        return $this->belongsTo(WorkPermit::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
