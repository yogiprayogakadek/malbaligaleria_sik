<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_permits', function (Blueprint $table) {
            $table->string('work_category', 40)->default('other')->after('work_location');
            $table->string('assigned_division', 5)->default('MEP')->after('work_category');
            $table->index(['assigned_division', 'status', 'created_at'], 'work_permits_division_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('work_permits', function (Blueprint $table) {
            $table->dropIndex('work_permits_division_status_index');
            $table->dropColumn(['work_category', 'assigned_division']);
        });
    }
};
