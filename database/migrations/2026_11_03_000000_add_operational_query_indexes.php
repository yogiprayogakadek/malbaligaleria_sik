<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loading_permits', function (Blueprint $table): void {
            $table->index(['status', 'sla_started_at'], 'loading_status_sla_idx');
        });

        Schema::table('work_permits', function (Blueprint $table): void {
            $table->index(['permit_kind', 'status', 'sla_started_at'], 'work_kind_status_sla_idx');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->index(['role', 'division', 'is_active'], 'users_role_div_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex('users_role_div_active_idx'));
        Schema::table('work_permits', fn (Blueprint $table) => $table->dropIndex('work_kind_status_sla_idx'));
        Schema::table('loading_permits', fn (Blueprint $table) => $table->dropIndex('loading_status_sla_idx'));
    }
};
