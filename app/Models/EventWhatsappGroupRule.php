<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EventWhatsappGroupRule extends Model
{
    public const LOADING_PERMIT_CONDITION = 'Surat Loading/Unloading';

    protected $fillable = ['name', 'requirement_group', 'event_requirement_id', 'message', 'priority', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'priority' => 'integer'];
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(EventRequirement::class, 'event_requirement_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(EventWhatsappGroupMember::class, 'event_whatsapp_group_rule_members', 'rule_id', 'member_id');
    }

    public function getConditionLabelAttribute(): string
    {
        if ($this->requirement_group === self::LOADING_PERMIT_CONDITION) {
            return 'Surat tambahan dipilih';
        }

        return $this->requirement?->name ?? 'Semua pilihan dalam kelompok';
    }
}
