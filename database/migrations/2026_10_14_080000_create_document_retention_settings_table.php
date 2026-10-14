<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loading_permits', function (Blueprint $table): void {
            $table->string('id_doc_path')->nullable()->change();
        });
        Schema::table('work_permits', function (Blueprint $table): void {
            $table->string('id_doc_path')->nullable()->change();
        });

        Schema::create('document_retention_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('identity_days')->default(30);
            $table->unsignedSmallInteger('assignment_days')->default(90);
            $table->unsignedSmallInteger('financial_days')->default(365);
            $table->timestamps();
        });

        DB::table('document_retention_settings')->insert([
            'id' => 1,
            'enabled' => false,
            'identity_days' => 30,
            'assignment_days' => 90,
            'financial_days' => 365,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_retention_settings');

        DB::table('loading_permits')->whereNull('id_doc_path')->update(['id_doc_path' => '']);
        DB::table('work_permits')->whereNull('id_doc_path')->update(['id_doc_path' => '']);
        Schema::table('loading_permits', function (Blueprint $table): void {
            $table->string('id_doc_path')->nullable(false)->change();
        });
        Schema::table('work_permits', function (Blueprint $table): void {
            $table->string('id_doc_path')->nullable(false)->change();
        });
    }
};
