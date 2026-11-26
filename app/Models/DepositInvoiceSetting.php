<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepositInvoiceSetting extends Model
{
    public const MODE_AUTOMATIC = 'automatic';

    public const MODE_MANUAL = 'manual';

    public const SIGNATURE_BARCODE = 'barcode';

    public const SIGNATURE_UPLOAD = 'upload';

    protected $fillable = [
        'mode',
        'issuer_name',
        'issuer_title',
        'signature_method',
        'signature_path',
        'signature_sha256',
        'payment_instructions',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1], [
            'mode' => self::MODE_MANUAL,
            'issuer_name' => 'Mal Bali Galeria',
            'issuer_title' => 'Property Management',
            'signature_method' => self::SIGNATURE_BARCODE,
        ]);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(DepositInvoiceAccount::class, 'setting_id')->orderBy('sort_order')->orderBy('id');
    }

    public function isAutomatic(): bool
    {
        return $this->mode === self::MODE_AUTOMATIC;
    }

    public function usesUploadedSignature(): bool
    {
        return $this->signature_method === self::SIGNATURE_UPLOAD;
    }
}
