<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loading_permits', function (Blueprint $table): void {
            $table->char('submission_token_hash', 64)->nullable()->unique()->after('user_id');
        });

        Schema::table('work_permits', function (Blueprint $table): void {
            $table->char('submission_token_hash', 64)->nullable()->unique()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('work_permits', function (Blueprint $table): void {
            $table->dropUnique(['submission_token_hash']);
            $table->dropColumn('submission_token_hash');
        });

        Schema::table('loading_permits', function (Blueprint $table): void {
            $table->dropUnique(['submission_token_hash']);
            $table->dropColumn('submission_token_hash');
        });
    }
};
