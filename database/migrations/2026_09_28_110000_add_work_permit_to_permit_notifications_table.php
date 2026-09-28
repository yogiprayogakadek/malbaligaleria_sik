<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permit_notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('permit_id')->nullable()->change();
            $table->foreignId('work_permit_id')
                ->nullable()
                ->after('permit_id')
                ->constrained('work_permits')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('permit_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_permit_id');
            $table->unsignedBigInteger('permit_id')->nullable(false)->change();
        });
    }
};
