<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_number_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('permit_type', 20)->unique();
            $table->string('pattern', 100);
            $table->unsignedTinyInteger('sequence_padding')->default(4);
            $table->string('reset_period', 20)->default('global');
            $table->timestamps();
        });

        DB::table('permit_number_settings')->insert([
            [
                'permit_type' => 'loading',
                'pattern' => 'MBG/SIK/{month_roman}/{sequence}',
                'sequence_padding' => 4,
                'reset_period' => 'monthly',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'permit_type' => 'work',
                'pattern' => 'MBG/SIK/{month_roman}/{year}/{sequence}',
                'sequence_padding' => 4,
                'reset_period' => 'global',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_number_settings');
    }
};
