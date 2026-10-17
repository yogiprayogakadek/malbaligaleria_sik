<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('max_attempts')->default(10);
            $table->unsignedSmallInteger('decay_seconds')->default(60);
            $table->timestamps();
        });

        DB::table('announcement_settings')->insert([
            'id' => 1,
            'max_attempts' => 10,
            'decay_seconds' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_settings');
    }
};
