<?php

namespace App\Models;

use App\Support\IndonesianPhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EventWhatsappGroupMember extends Model
{
    protected $fillable = ['name', 'phone', 'phone_hash', 'mention_by_default', 'is_active'];

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'mention_by_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function rules(): BelongsToMany
    {
        return $this->belongsToMany(EventWhatsappGroupRule::class, 'event_whatsapp_group_rule_members', 'member_id', 'rule_id');
    }

    public function mentionJid(): ?string
    {
        $phone = IndonesianPhoneNumber::normalize($this->phone);

        return $phone ? $phone.'@c.us' : null;
    }
}
