<?php

use App\Services\AuditIntegrityService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $integrity = app(AuditIntegrityService::class);
        if (! $integrity->keyIsValid()) {
            throw new RuntimeException('AUDIT_CHAIN_KEY harus berupa base64 dari minimal 32 byte sebelum migration dijalankan.');
        }

        $addChainUuid = ! Schema::hasColumn('audit_logs', 'chain_uuid');
        $addPreviousHash = ! Schema::hasColumn('audit_logs', 'previous_hash');
        $addEntryHash = ! Schema::hasColumn('audit_logs', 'entry_hash');
        if ($addChainUuid || $addPreviousHash || $addEntryHash) {
            Schema::table('audit_logs', function (Blueprint $table) use ($addChainUuid, $addPreviousHash, $addEntryHash): void {
                if ($addChainUuid) {
                    $table->uuid('chain_uuid')->nullable()->unique();
                }
                if ($addPreviousHash) {
                    $table->char('previous_hash', 64)->nullable();
                }
                if ($addEntryHash) {
                    $table->char('entry_hash', 64)->nullable()->index();
                }
            });
        }

        if (! Schema::hasTable('audit_chain_state')) {
            Schema::create('audit_chain_state', function (Blueprint $table): void {
                $table->unsignedTinyInteger('id')->primary();
                $table->unsignedBigInteger('last_log_id')->nullable();
                $table->char('last_hash', 64)->nullable();
                $table->timestamps();
            });
        }

        DB::table('audit_chain_state')->updateOrInsert(
            ['id' => 1],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $integrity->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_chain_state');
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropUnique(['chain_uuid']);
            $table->dropIndex(['entry_hash']);
            $table->dropColumn(['chain_uuid', 'previous_hash', 'entry_hash']);
        });
    }
};
