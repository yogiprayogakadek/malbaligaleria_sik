<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loading_permits', function (Blueprint $table) {
            $table->time('movement_time')->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('loading_permits', function (Blueprint $table) {
            $table->dropColumn('movement_time');
        });
    }
};
