<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loading_permits', function (Blueprint $table): void {
            $table->char('letter_code', 8)->nullable()->unique()->after('barcode_token');
        });
    }

    public function down(): void
    {
        Schema::table('loading_permits', function (Blueprint $table): void {
            $table->dropUnique('loading_permits_letter_code_unique');
            $table->dropColumn('letter_code');
        });
    }
};
