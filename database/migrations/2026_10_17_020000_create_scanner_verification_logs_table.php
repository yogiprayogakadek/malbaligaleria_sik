<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanner_verification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('token_hash', 64)->index();
            $table->string('permit_type', 20)->nullable()->index();
            $table->unsignedBigInteger('permit_id')->nullable()->index();
            $table->string('result', 30)->index();
            $table->unsignedSmallInteger('status_code');
            $table->text('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('latitude')->nullable();
            $table->text('longitude')->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['permit_type', 'permit_id']);
            $table->index(['result', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanner_verification_logs');
    }
};
