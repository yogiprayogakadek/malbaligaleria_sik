<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table): void {
            $table->timestamp('previewed_at')->nullable()->after('uploaded_by');
            $table->foreignId('previewed_by')->nullable()->after('previewed_at')->constrained('users')->nullOnDelete();
            $table->char('preview_sha256', 64)->nullable()->after('previewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('previewed_by');
            $table->dropColumn(['previewed_at', 'preview_sha256']);
        });
    }
};
