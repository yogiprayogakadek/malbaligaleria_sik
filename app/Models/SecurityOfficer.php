<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecurityOfficer extends Model
{
    protected $fillable = [
        'name', 'officer_id', 'whatsapp', 'pin_hash', 'is_active',
        'pin_rotated_at', 'last_authenticated_at',
    ];

    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'pin_rotated_at' => 'datetime',
            'last_authenticated_at' => 'datetime',
        ];
    }

    public function gateEvents(): HasMany
    {
        return $this->hasMany(SecurityGateEvent::class);
    }
}
