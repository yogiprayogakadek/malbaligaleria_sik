<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loading_permits', function (Blueprint $table): void {
            $table->foreignId('assigned_validator_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_validator_id');
            $table->string('assignment_note', 500)->nullable()->after('assigned_at');
            $table->index(['status', 'assigned_validator_id'], 'loading_permits_assignment_index');
        });

        Schema::table('work_permits', function (Blueprint $table): void {
            $table->foreignId('assigned_validator_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_validator_id');
            $table->string('assignment_note', 500)->nullable()->after('assigned_at');
            $table->index(['status', 'assigned_validator_id'], 'work_permits_assignment_index');
        });
    }

    public function down(): void
    {
        Schema::table('work_permits', function (Blueprint $table): void {
            $table->dropIndex('work_permits_assignment_index');
            $table->dropConstrainedForeignId('assigned_validator_id');
            $table->dropColumn(['assigned_at', 'assignment_note']);
        });

        Schema::table('loading_permits', function (Blueprint $table): void {
            $table->dropIndex('loading_permits_assignment_index');
            $table->dropConstrainedForeignId('assigned_validator_id');
            $table->dropColumn(['assigned_at', 'assignment_note']);
        });
    }
};
