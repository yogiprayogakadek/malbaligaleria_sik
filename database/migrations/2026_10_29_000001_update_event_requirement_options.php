<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('event_requirements')
            ->where('group_name', 'Akses kendaraan')
            ->where('name', 'Pintu Keluar Utara')
            ->update(['name' => 'Pintu Keluar/Masuk Utara (Maks. Mobil Pickup)', 'updated_at' => $now]);

        DB::table('event_requirements')
            ->where('group_name', 'Akses kendaraan')
            ->where('name', 'Pintu Loading Dock XXI')
            ->update(['name' => 'Pintu Loading Dock XXI (Special Case)', 'updated_at' => $now]);

        foreach ([
            ['Akses loading barang', 'Loading Dock 1 (MBG Extension)', 42],
            ['Akses loading barang', 'Loading Dock 2 (Ryoshi)', 44],
            ['Fasilitas', 'Sliding Door Main Lobby Extension', 92],
            ['Fasilitas', 'Lainnya', 94],
            ['Akses kendaraan', 'PKU/Pintu Masuk Utara (Tinggi Maks: Pickup)', 72],
        ] as [$group, $name, $order]) {
            DB::table('event_requirements')->updateOrInsert(
                ['group_name' => $group, 'name' => $name],
                ['is_active' => true, 'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        DB::table('event_requirements')
            ->where('group_name', 'Akses kendaraan')
            ->where('name', 'Pintu Keluar/Masuk Utara (Maks. Mobil Pickup)')
            ->update(['name' => 'Pintu Keluar Utara', 'updated_at' => now()]);

        DB::table('event_requirements')
            ->where('group_name', 'Akses kendaraan')
            ->where('name', 'Pintu Loading Dock XXI (Special Case)')
            ->update(['name' => 'Pintu Loading Dock XXI', 'updated_at' => now()]);

        foreach ([
            ['Akses loading barang', 'Loading Dock 1 (MBG Extension)'],
            ['Akses loading barang', 'Loading Dock 2 (Ryoshi)'],
            ['Fasilitas', 'Sliding Door Main Lobby Extension'],
            ['Fasilitas', 'Lainnya'],
            ['Akses kendaraan', 'PKU/Pintu Masuk Utara (Tinggi Maks: Pickup)'],
        ] as [$group, $name]) {
            DB::table('event_requirements')
                ->where('group_name', $group)
                ->where('name', $name)
                ->delete();
        }
    }
};
