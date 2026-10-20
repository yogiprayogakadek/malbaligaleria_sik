<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessageLog extends Model
{
    protected $fillable = [
        'unique_key',
        'user_id',
        'permit_type',
        'permit_id',
        'event',
        'recipient_kind',
        'recipient_name',
        'recipient_phone',
        'recipient_phone_hash',
        'message',
        'status',
        'attempts',
        'provider_message_id',
        'error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'recipient_phone' => 'encrypted',
            'message' => 'encrypted',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function maskedRecipient(): string
    {
        $phone = preg_replace('/\D/', '', (string) $this->recipient_phone);

        return strlen($phone) > 7 ? substr($phone, 0, 4).str_repeat('*', strlen($phone) - 7).substr($phone, -3) : 'Nomor dilindungi';
    }
}
