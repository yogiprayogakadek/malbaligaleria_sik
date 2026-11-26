<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_letter_codes', function (Blueprint $table): void {
            $table->id();
            $table->char('code', 8)->unique();
            $table->string('permit_type', 10);
            $table->unsignedBigInteger('permit_id');
            $table->uuid('document_id')->nullable();
            $table->string('active_permit_key')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('issued_at');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->index(['permit_type', 'permit_id', 'is_active'], 'official_letter_codes_permit_active_index');
        });

        DB::table('loading_permits')
            ->whereNotNull('letter_code')
            ->orderBy('id')
            ->chunkById(500, function ($permits): void {
                $now = now();
                DB::table('official_letter_codes')->insertOrIgnore($permits->map(fn ($permit): array => [
                    'code' => strtoupper((string) $permit->letter_code),
                    'permit_type' => 'loading',
                    'permit_id' => $permit->id,
                    'document_id' => $permit->official_document_id,
                    'active_permit_key' => 'loading:'.$permit->id,
                    'is_active' => true,
                    'issued_at' => $permit->official_document_issued_at ?: $now,
                    'retired_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

        Schema::table('official_document_versions', function (Blueprint $table): void {
            $table->char('letter_code', 8)->nullable()->after('document_id');
        });

        Schema::table('loading_permits', function (Blueprint $table): void {
            $table->dropUnique('loading_permits_letter_code_unique');
            $table->dropColumn('letter_code');
        });
    }

    public function down(): void
    {
        Schema::table('loading_permits', function (Blueprint $table): void {
            $table->char('letter_code', 8)->nullable()->unique()->after('barcode_token');
        });

        DB::table('official_letter_codes')
            ->where('permit_type', 'loading')
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function ($record): void {
                DB::table('loading_permits')
                    ->where('id', $record->permit_id)
                    ->update(['letter_code' => $record->code]);
            });

        Schema::table('official_document_versions', function (Blueprint $table): void {
            $table->dropColumn('letter_code');
        });

        Schema::dropIfExists('official_letter_codes');
    }
};
