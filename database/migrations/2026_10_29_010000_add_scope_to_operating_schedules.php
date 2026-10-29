<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operating_schedules', function (Blueprint $table): void {
            $table->string('scope', 20)->default('general')->after('id');
            $table->dropUnique('operating_schedules_day_of_week_unique');
            $table->unique(['scope', 'day_of_week']);
        });

        Schema::table('operating_schedule_exceptions', function (Blueprint $table): void {
            $table->string('scope', 20)->default('general')->after('id');
            $table->dropUnique('operating_schedule_exceptions_date_unique');
            $table->unique(['scope', 'date']);
        });

        $now = now();
        $eventSchedules = DB::table('operating_schedules')
            ->where('scope', 'general')
            ->orderBy('day_of_week')
            ->get()
            ->map(fn (object $schedule): array => [
                'scope' => 'event',
                'day_of_week' => $schedule->day_of_week,
                'is_open' => $schedule->is_open,
                'is_24_hours' => $schedule->is_24_hours,
                'opens_at' => $schedule->opens_at,
                'closes_at' => $schedule->closes_at,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

        if ($eventSchedules !== []) {
            DB::table('operating_schedules')->insert($eventSchedules);
        }

        $eventExceptions = DB::table('operating_schedule_exceptions')
            ->where('scope', 'general')
            ->orderBy('date')
            ->get()
            ->map(fn (object $exception): array => [
                'scope' => 'event',
                'date' => $exception->date,
                'label' => $exception->label,
                'is_open' => $exception->is_open,
                'is_24_hours' => $exception->is_24_hours,
                'opens_at' => $exception->opens_at,
                'closes_at' => $exception->closes_at,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

        if ($eventExceptions !== []) {
            DB::table('operating_schedule_exceptions')->insert($eventExceptions);
        }
    }

    public function down(): void
    {
        DB::table('operating_schedule_exceptions')->where('scope', 'event')->delete();
        DB::table('operating_schedules')->where('scope', 'event')->delete();

        Schema::table('operating_schedule_exceptions', function (Blueprint $table): void {
            $table->dropUnique('operating_schedule_exceptions_scope_date_unique');
            $table->dropColumn('scope');
            $table->unique('date');
        });

        Schema::table('operating_schedules', function (Blueprint $table): void {
            $table->dropUnique('operating_schedules_scope_day_of_week_unique');
            $table->dropColumn('scope');
            $table->unique('day_of_week');
        });
    }
};
