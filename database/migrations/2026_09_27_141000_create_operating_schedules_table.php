<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operating_schedules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('day_of_week')->unique();
            $table->boolean('is_open')->default(true);
            $table->boolean('is_24_hours')->default(true);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('operating_schedules')->insert(array_map(
            static fn (int $day): array => [
                'day_of_week' => $day,
                'is_open' => true,
                'is_24_hours' => true,
                'opens_at' => null,
                'closes_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            range(0, 6),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('operating_schedules');
    }
};
