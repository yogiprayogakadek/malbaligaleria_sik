<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['loading_permits', 'work_permits'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('official_approved_by_name', 150)->nullable()->after('official_document_issued_at');
                $table->string('official_approved_by_division', 80)->nullable()->after('official_approved_by_name');
            });
        }
    }

    public function down(): void
    {
        foreach (['loading_permits', 'work_permits'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['official_approved_by_name', 'official_approved_by_division']);
            });
        }
    }
};
