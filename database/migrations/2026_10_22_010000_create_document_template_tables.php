<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_template_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('converter_enabled')->default(false);
            $table->string('converter_scheme', 8)->default('http');
            $table->string('converter_host', 45)->default('192.168.0.249');
            $table->unsignedSmallInteger('converter_port')->default(3000);
            $table->text('converter_token')->nullable();
            $table->unsignedSmallInteger('converter_timeout')->default(60);
            $table->string('loading_renderer', 16)->default('default');
            $table->string('work_renderer', 16)->default('default');
            $table->string('event_renderer', 16)->default('default');
            $table->boolean('fallback_enabled')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_succeeded')->nullable();
            $table->string('last_test_message', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('document_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('permit_type', 16)->index();
            $table->unsignedInteger('version');
            $table->string('original_name');
            $table->string('storage_path');
            $table->char('sha256', 64);
            $table->unsignedBigInteger('size');
            $table->json('placeholders');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['permit_type', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('document_template_settings');
    }
};
