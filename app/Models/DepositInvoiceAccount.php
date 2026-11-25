<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositInvoiceAccount extends Model
{
    protected $fillable = ['bank_name', 'account_number', 'account_holder', 'sort_order'];

    protected function casts(): array
    {
        return ['account_number' => 'encrypted', 'sort_order' => 'integer'];
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(DepositInvoiceSetting::class, 'setting_id');
    }
}
