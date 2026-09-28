<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_permits', function (Blueprint $table) {
            $table->json('work_schedules')->nullable()->after('work_schedule');
        });

        DB::table('work_permits')
            ->whereNotNull('work_schedule')
            ->orderBy('id')
            ->eachById(function (object $permit): void {
                DB::table('work_permits')
                    ->where('id', $permit->id)
                    ->update(['work_schedules' => json_encode([$permit->work_schedule], JSON_THROW_ON_ERROR)]);
            });
    }

    public function down(): void
    {
        Schema::table('work_permits', function (Blueprint $table) {
            $table->dropColumn('work_schedules');
        });
    }
};
