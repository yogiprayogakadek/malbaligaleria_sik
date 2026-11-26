<?php

namespace App\Models\Concerns;

use App\Models\OfficialLetterCode;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasOfficialLetterCode
{
    abstract public function officialLetterCodeType(): string;

    public function officialLetterCodeRelationTypes(): array
    {
        return [$this->officialLetterCodeType()];
    }

    public function letterCodeHistory(): HasMany
    {
        return $this->hasMany(OfficialLetterCode::class, 'permit_id')
            ->whereIn('permit_type', $this->officialLetterCodeRelationTypes())
            ->latest('id');
    }

    public function activeLetterCode(): HasOne
    {
        return $this->hasOne(OfficialLetterCode::class, 'permit_id')
            ->whereIn('permit_type', $this->officialLetterCodeRelationTypes())
            ->where('is_active', true)
            ->latestOfMany('id');
    }

    public function getLetterCodeAttribute(): ?string
    {
        $record = $this->relationLoaded('activeLetterCode')
            ? $this->getRelation('activeLetterCode')
            : $this->activeLetterCode()->first();

        $this->setRelation('activeLetterCode', $record);

        return $record?->code;
    }

    public function getFormattedLetterCodeAttribute(): ?string
    {
        return $this->letter_code
            ? substr($this->letter_code, 0, 4).'-'.substr($this->letter_code, 4)
            : null;
    }
}
