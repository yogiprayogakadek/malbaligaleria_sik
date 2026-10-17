<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementRecipient extends Model
{
    protected $fillable = [
        'announcement_campaign_id',
        'channel',
        'audience',
        'user_id',
        'recipient_name',
        'destination',
        'destination_hash',
        'status',
        'attempts',
        'provider_message_id',
        'error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'destination' => 'encrypted',
            'error' => 'encrypted',
            'sent_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AnnouncementCampaign::class, 'announcement_campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
