<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loading_permits', function (Blueprint $table) {
            $table->string('document_token', 64)->nullable()->unique()->after('id_doc_type');
        });

        DB::table('loading_permits')
            ->whereNull('document_token')
            ->orderBy('id')
            ->eachById(function (object $permit): void {
                DB::table('loading_permits')
                    ->where('id', $permit->id)
                    ->update(['document_token' => Str::random(64)]);
            });
    }

    public function down(): void
    {
        Schema::table('loading_permits', function (Blueprint $table) {
            $table->dropUnique(['document_token']);
            $table->dropColumn('document_token');
        });
    }
};
