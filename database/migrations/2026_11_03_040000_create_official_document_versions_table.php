<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_permits', function (Blueprint $table): void {
            $table->string('official_scan_token', 64)->nullable()->unique()->after('public_token');
        });

        Schema::create('official_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('permit_type', 10);
            $table->unsignedBigInteger('permit_id');
            $table->unsignedInteger('version');
            $table->string('permit_number');
            $table->uuid('document_id')->unique();
            $table->string('storage_path');
            $table->char('sha256', 64);
            $table->timestamp('issued_at');
            $table->string('approved_by_name', 150)->nullable();
            $table->string('approved_by_division', 100)->nullable();
            $table->timestamp('replaced_at');
            $table->foreignId('replaced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('replacement_reason');
            $table->uuid('replacement_document_id');
            $table->timestamps();

            $table->unique(['permit_type', 'permit_id', 'version'], 'official_document_versions_permit_version_unique');
            $table->index(['permit_type', 'permit_id'], 'official_document_versions_permit_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_document_versions');

        Schema::table('work_permits', function (Blueprint $table): void {
            $table->dropUnique('work_permits_official_scan_token_unique');
            $table->dropColumn('official_scan_token');
        });
    }
};
