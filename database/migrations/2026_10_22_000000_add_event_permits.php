<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_permits', function (Blueprint $table): void {
            $table->string('permit_kind', 20)->default('work')->after('permit_number')->index();
            $table->string('event_type', 30)->nullable()->after('work_category');
            $table->json('event_requirements')->nullable()->after('event_type');
            $table->string('id_doc_path')->nullable()->change();
            $table->string('id_doc_type', 10)->nullable()->change();
        });

        Schema::create('event_requirements', function (Blueprint $table): void {
            $table->id();
            $table->string('group_name', 80);
            $table->string('name', 160);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->unique(['group_name', 'name']);
        });

        $now = now();
        DB::table('event_requirements')->insert([
            ['group_name' => 'Fasilitas', 'name' => 'Penerangan / Lampu area', 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['group_name' => 'Akses loading barang', 'name' => 'South Entrance', 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['group_name' => 'Akses loading barang', 'name' => 'Main Lobby Extension Entrance', 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['group_name' => 'Akses loading barang', 'name' => 'East Entrance (Travelator Red Dog)', 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
            ['group_name' => 'Akses kendaraan', 'name' => 'Pintu Keluar Utara', 'sort_order' => 50, 'created_at' => $now, 'updated_at' => $now],
            ['group_name' => 'Akses kendaraan', 'name' => 'Pintu Loading Dock XXI', 'sort_order' => 60, 'created_at' => $now, 'updated_at' => $now],
            ['group_name' => 'Akses kendaraan', 'name' => 'Pintu Masuk Barat (Videotron)', 'sort_order' => 70, 'created_at' => $now, 'updated_at' => $now],
            ['group_name' => 'Fasilitas', 'name' => 'AC MBG Extension', 'sort_order' => 80, 'created_at' => $now, 'updated_at' => $now],
            ['group_name' => 'Fasilitas', 'name' => 'Kelistrikan', 'sort_order' => 90, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('permit_number_settings')->insertOrIgnore([
            'permit_type' => 'event',
            'pattern' => 'MBG/EVENT/{month_roman}/{year}/{sequence}',
            'sequence_padding' => 4,
            'reset_period' => 'yearly',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('permit_number_settings')->where('permit_type', 'event')->delete();
        Schema::dropIfExists('event_requirements');
        DB::table('work_permits')->where('permit_kind', 'event')->delete();

        Schema::table('work_permits', function (Blueprint $table): void {
            $table->dropIndex(['permit_kind']);
            $table->dropColumn(['permit_kind', 'event_type', 'event_requirements']);
            $table->string('id_doc_path')->nullable(false)->change();
            $table->string('id_doc_type', 10)->nullable(false)->change();
        });
    }
};
