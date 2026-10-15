<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_revisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('batch_id');
            $table->morphs('revisable');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('field_key', 60);
            $table->text('reason');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['revisable_type', 'revisable_id', 'resolved_at'], 'permit_revisions_active_index');
            $table->index(['batch_id', 'field_key']);
        });

        Schema::table('loading_permits', function (Blueprint $table): void {
            $table->string('revision_return_status', 30)->nullable()->after('status');
        });
        Schema::table('work_permits', function (Blueprint $table): void {
            $table->string('revision_return_status', 30)->nullable()->after('status');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE loading_permits MODIFY status ENUM('pending','revision_required','approved','rejected') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('loading_permits')->where('status', 'revision_required')->update(['status' => 'pending']);
            DB::statement("ALTER TABLE loading_permits MODIFY status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('work_permits', function (Blueprint $table): void {
            $table->dropColumn('revision_return_status');
        });
        Schema::table('loading_permits', function (Blueprint $table): void {
            $table->dropColumn('revision_return_status');
        });
        Schema::dropIfExists('permit_revisions');
    }
};
