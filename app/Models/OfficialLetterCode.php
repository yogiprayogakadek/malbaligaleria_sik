<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficialLetterCode extends Model
{
    protected $fillable = [
        'code',
        'permit_type',
        'permit_id',
        'document_id',
        'active_permit_key',
        'is_active',
        'issued_at',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'issued_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    public function resolvePermit(): LoadingPermit|WorkPermit|null
    {
        return match ($this->permit_type) {
            'loading' => LoadingPermit::query()->find($this->permit_id),
            'work' => WorkPermit::query()->where('permit_kind', 'work')->find($this->permit_id),
            'event' => WorkPermit::query()->where('permit_kind', 'event')->find($this->permit_id),
            default => null,
        };
    }

    public function formatted(): string
    {
        return substr($this->code, 0, 4).'-'.substr($this->code, 4);
    }
}
