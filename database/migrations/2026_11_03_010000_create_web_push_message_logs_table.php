<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_push_message_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('subscription_id')->nullable()->index();
            $table->char('endpoint_hash', 64)->index();
            $table->string('title')->nullable();
            $table->string('tag')->nullable();
            $table->string('permit_type', 20)->nullable();
            $table->unsignedBigInteger('permit_id')->nullable();
            $table->string('status', 20)->index();
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('failure_reason')->nullable();
            $table->longText('payload')->nullable();
            $table->text('options')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['permit_type', 'permit_id'], 'web_push_permit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_push_message_logs');
    }
};
