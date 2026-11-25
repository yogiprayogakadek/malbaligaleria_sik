<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepositInvoiceSetting extends Model
{
    public const MODE_AUTOMATIC = 'automatic';

    public const MODE_MANUAL = 'manual';

    protected $fillable = ['mode', 'payment_instructions'];

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1], ['mode' => self::MODE_MANUAL]);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(DepositInvoiceAccount::class, 'setting_id')->orderBy('sort_order')->orderBy('id');
    }

    public function isAutomatic(): bool
    {
        return $this->mode === self::MODE_AUTOMATIC;
    }
}
