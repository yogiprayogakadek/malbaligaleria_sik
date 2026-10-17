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
                $table->timestamp('revoked_at')->nullable()->index();
                $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('revocation_reason')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['loading_permits', 'work_permits'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['revoked_by']);
                $table->dropColumn(['revoked_at', 'revoked_by', 'revocation_reason']);
            });
        }
    }
};
