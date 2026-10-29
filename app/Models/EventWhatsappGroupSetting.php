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

    protected $fillable = [
        'is_active',
        'group_name',
        'group_id',
        'group_id_hash',
        'base_message',
        'group_sync_max_attempts',
        'group_sync_decay_seconds',
        'outbound_max_attempts',
        'outbound_decay_seconds',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'group_id' => 'encrypted',
            'group_sync_max_attempts' => 'integer',
            'group_sync_decay_seconds' => 'integer',
            'outbound_max_attempts' => 'integer',
            'outbound_decay_seconds' => 'integer',
        ];
    }

    public static function current(): ?self
    {
        return self::query()->latest('id')->first();
    }
}
