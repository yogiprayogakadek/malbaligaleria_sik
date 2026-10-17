<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnouncementCampaign extends Model
{
    protected $fillable = [
        'created_by',
        'submission_token_hash',
        'title',
        'message',
        'channels',
        'audiences',
        'status',
        'recipient_count',
        'sent_count',
        'failed_count',
        'cancelled_count',
        'started_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'audiences' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(AnnouncementRecipient::class);
    }

    public function refreshProgress(): void
    {
        $counts = $this->recipients()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $pending = (int) ($counts['queued'] ?? 0) + (int) ($counts['processing'] ?? 0);

        $this->forceFill([
            'status' => $this->cancelled_at ? 'cancelled' : ($pending === 0 ? 'completed' : 'processing'),
            'sent_count' => (int) ($counts['sent'] ?? 0),
            'failed_count' => (int) ($counts['failed'] ?? 0) + (int) ($counts['skipped'] ?? 0),
            'cancelled_count' => (int) ($counts['cancelled'] ?? 0),
            'started_at' => $this->started_at ?? now(),
            'completed_at' => $pending === 0 ? ($this->completed_at ?? now()) : null,
        ])->save();
    }
}
