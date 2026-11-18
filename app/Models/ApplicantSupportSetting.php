<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicantSupportSetting extends Model
{
    protected $fillable = ['is_active', 'contact_name', 'whatsapp_number'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1], ['is_active' => false]);
    }

    public function isAvailable(): bool
    {
        return $this->is_active && filled($this->contact_name) && filled($this->whatsapp_number);
    }
}
