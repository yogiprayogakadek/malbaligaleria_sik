<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('loading_hours')->default(8);
            $table->unsignedSmallInteger('work_hours')->default(24);
            $table->timestamps();
        });

        DB::table('sla_settings')->insert([
            'id' => 1,
            'loading_hours' => 8,
            'work_hours' => 24,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('loading_permits', function (Blueprint $table): void {
            $table->timestamp('sla_started_at')->nullable()->index();
            $table->timestamp('sla_escalated_at')->nullable();
            $table->string('sla_escalated_status', 40)->nullable();
        });

        Schema::table('work_permits', function (Blueprint $table): void {
            $table->timestamp('sla_started_at')->nullable()->index();
            $table->timestamp('sla_escalated_at')->nullable();
            $table->string('sla_escalated_status', 40)->nullable();
        });

        $startedAt = now();
        DB::table('loading_permits')->where('status', 'pending')->update(['sla_started_at' => $startedAt]);
        DB::table('work_permits')
            ->where(function ($query): void {
                $query->whereIn('status', ['tr_review', 'mep_review', 'payment_review', 'mep_final_review', 'refund_processing'])
                    ->orWhere(function ($completed): void {
                        $completed->where('status', 'completed')->where('deposit_required', true);
                    });
            })
            ->update(['sla_started_at' => $startedAt]);
    }

    public function down(): void
    {
        Schema::table('work_permits', function (Blueprint $table): void {
            $table->dropColumn(['sla_started_at', 'sla_escalated_at', 'sla_escalated_status']);
        });

        Schema::table('loading_permits', function (Blueprint $table): void {
            $table->dropColumn(['sla_started_at', 'sla_escalated_at', 'sla_escalated_status']);
        });

        Schema::dropIfExists('sla_settings');
    }
};
