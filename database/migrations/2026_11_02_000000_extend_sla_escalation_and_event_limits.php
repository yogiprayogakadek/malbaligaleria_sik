<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('event_hours')->default(24);
        });

        Schema::table('loading_permits', function (Blueprint $table): void {
            $table->unsignedTinyInteger('sla_escalation_level')->default(0);
        });

        Schema::table('work_permits', function (Blueprint $table): void {
            $table->unsignedTinyInteger('sla_escalation_level')->default(0);
        });

        DB::table('loading_permits')->whereNotNull('sla_escalated_at')->update(['sla_escalation_level' => 1]);
        DB::table('work_permits')->whereNotNull('sla_escalated_at')->update(['sla_escalation_level' => 1]);
    }

    public function down(): void
    {
        Schema::table('work_permits', fn (Blueprint $table) => $table->dropColumn('sla_escalation_level'));
        Schema::table('loading_permits', fn (Blueprint $table) => $table->dropColumn('sla_escalation_level'));
        Schema::table('sla_settings', fn (Blueprint $table) => $table->dropColumn('event_hours'));
    }
};
