<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scanner_verification_logs', function (Blueprint $table): void {
            $table->string('ip_hash', 64)->nullable()->index()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('scanner_verification_logs', function (Blueprint $table): void {
            $table->dropColumn('ip_hash');
        });
    }
};
