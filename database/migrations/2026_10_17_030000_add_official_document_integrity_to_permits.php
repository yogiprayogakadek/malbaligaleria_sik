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
                $table->uuid('official_document_id')->nullable()->unique();
                $table->string('official_document_path')->nullable();
                $table->char('official_document_sha256', 64)->nullable();
                $table->timestamp('official_document_issued_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['loading_permits', 'work_permits'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropUnique($tableName.'_official_document_id_unique');
                $table->dropColumn([
                    'official_document_id',
                    'official_document_path',
                    'official_document_sha256',
                    'official_document_issued_at',
                ]);
            });
        }
    }
};
