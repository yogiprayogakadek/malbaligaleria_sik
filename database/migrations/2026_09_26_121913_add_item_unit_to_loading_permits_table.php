<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loading_permits', function (Blueprint $table) {
            $table->string('item_unit', 30)->default('pcs')->after('item_count');
            $table->text('item_description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loading_permits', function (Blueprint $table) {
            $table->dropColumn('item_unit');
            $table->text('item_description')->nullable(false)->change();
        });
    }
};
