<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidatorDelegation extends Model
{
    public const DIVISIONS = ['TR', 'MEP', 'EP', 'FIN'];

    protected $fillable = [
        'validator_id',
        'division',
        'starts_on',
        'ends_on',
        'reason',
        'created_by',
        'revoked_at',
        'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'revoked_at' => 'datetime',
        ];
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validator_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today());
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && $this->starts_on->lte(today())
            && $this->ends_on->gte(today());
    }

    public static function divisionLabel(string $division): string
    {
        return match ($division) {
            'TR' => 'Tenant Relationship',
            'MEP' => 'Engineering',
            'EP' => 'Event Promotion',
            'FIN' => 'Finance',
            default => $division,
        };
    }
}
