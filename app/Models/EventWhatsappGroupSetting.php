<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventWhatsappGroupSetting extends Model
{
    public const DEFAULT_MESSAGE = <<<'TEXT'
*Surat Izin Event Disetujui*

EO/Tenant: {{organizer_name}}
Nomor surat: {{permit_number}}
Kategori: {{category}}
Lokasi: {{location}}
Periode: {{period}}

Fasilitas: {{facilities}}
Akses loading: {{loading_access}}
Akses kendaraan: {{vehicle_access}}

{{conditional_messages}}
{{mentions}}
TEXT;

    protected $fillable = ['is_active', 'group_name', 'group_id', 'group_id_hash', 'base_message'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'group_id' => 'encrypted',
        ];
    }

    public static function current(): ?self
    {
        return self::query()->latest('id')->first();
    }
}
