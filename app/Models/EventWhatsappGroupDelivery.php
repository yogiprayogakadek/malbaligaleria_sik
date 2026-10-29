<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventWhatsappGroupDelivery extends Model
{
    protected $fillable = [
        'work_permit_id', 'setting_id', 'unique_key', 'group_id', 'group_id_hash', 'message', 'mentions', 'filename',
        'text_status', 'file_status', 'attempts', 'text_provider_message_id', 'file_provider_message_id', 'error', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'group_id' => 'encrypted',
            'message' => 'encrypted',
            'mentions' => 'encrypted:array',
            'sent_at' => 'datetime',
        ];
    }

    public function permit(): BelongsTo
    {
        return $this->belongsTo(WorkPermit::class, 'work_permit_id');
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(EventWhatsappGroupSetting::class, 'setting_id');
    }
}
