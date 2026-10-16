<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_submission_limit_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('max_per_minute')->default(10);
            $table->unsignedSmallInteger('max_per_day')->default(25);
            $table->timestamps();
        });

        DB::table('permit_submission_limit_settings')->insert([
            'id' => 1,
            'enabled' => true,
            'max_per_minute' => 10,
            'max_per_day' => 25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_submission_limit_settings');
    }
};
